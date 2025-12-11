<?php

declare(strict_types = 1);

namespace Sop\JGP;

/**
 * Event-driven recursion free SAX-style parser for JSON fed by generator.
 *
 * Only UTF-8 character encoding is supported.
 *
 * Optional non-strict mode permits unclosed structures, which is a common
 * occurrence with LLM generated JSON.
 *
 * @see https://www.json.org/
 * @see https://datatracker.ietf.org/doc/html/rfc7159
 * @see https://ecma-international.org/publications-and-standards/standards/ecma-404/
 */
class JSONGeneratorParser
{
    /**
     * Enumeration for object structure type.
     *
     * @var int
     */
    private const STRUCTURE_OBJECT = 1;

    /**
     * Enumeration for array structure type.
     *
     * @var int
     */
    private const STRUCTURE_ARRAY = 2;

    /**
     * Stack of currently parsed structures.
     *
     * @var array
     */
    private $structures = [];

    /**
     * Current line number.
     *
     * @var int
     */
    private $lineNum;

    /**
     * Current character number on the line.
     *
     * @var int
     */
    private $charNum;

    /**
     * Current nested structure depth.
     *
     * @var int
     */
    private $depth;

    /**
     * Constructor.
     *
     * @param JSONParserListener $listener Listener
     * @param bool               $strict   Enforce strict parsing rules
     * @param int                $maxDepth Maximum nested structure depth, 0 to disable
     */
    public function __construct(
        private JSONParserListener $listener,
        private bool $strict = true,
        private int $maxDepth = 512
    ) {}

    /**
     * Parse JSON from generator.
     *
     * Input may be optionally skipped to first structured (object or array)
     * element thus ignoring any junk before the actual JSON payload.
     *
     * @param \Generator $generator        Generator that yields string chunks
     * @param bool       $first_structured Whether to seek first strucuted element
     *                                     and ignore surrounding characters
     *
     * @return bool False if parsing would have failed in strict mode
     *
     * @throws JSONParserException
     */
    public function parse(\Generator $generator, bool $first_structured = false): bool
    {
        $this->lineNum = 1;
        $this->charNum = 1;
        $this->depth = 0;
        // transformer generator to yield single characters
        $chars = (function (\Generator $chunks) {
            while ($chunks->valid()) {
                $chunk = (string) $chunks->current();
                // Operate with bytes, since all JSON tokens are ASCII strings.
                // UTF-8 sequences are decoded by string parsers.
                for ($i = 0, $len = strlen($chunk); $i < $len; ++$i) {
                    $c = $chunk[$i];
                    yield $c;
                    if ("\n" === $c) {
                        ++$this->lineNum;
                        $this->charNum = 1;
                    } else {
                        ++$this->charNum;
                    }
                }
                $chunks->next();
            }
        })($generator);
        $this->_parseBom($chars);
        if ($first_structured) {
            while (!in_array($chars->current(), ['{', '['])) {
                $chars->next();
                if (!$chars->valid()) {
                    $this->_raiseError('EOF before first structured element');
                }
            }
        }
        $this->listener->startDocument();
        $ret = $this->_parseDocument($chars);
        if (!$first_structured) {
            if ($chars->valid()) {
                $this->_raiseError(sprintf(
                    'Expected EOF, got %s',
                    var_export($chars->current(), true)
                ));
            }
        }
        $this->listener->endDocument();
        return $ret;
    }

    /**
     * Parse byte order mark.
     */
    private function _parseBom(\Generator $chars): void
    {
        // UTF-8
        if ("\xEF" === $chars->current()) {
            try {
                $this->_expectLiteral("\xEF\xBB\xBF", $chars);
            } catch (\Exception $e) {
                $this->_raiseError('Invalid byte order mark');
            }
            return;
        }
        switch ($chars->current()) {
            case "\xFE":
                // UTF-16BE
                $this->_expectLiteral("\xFE\xFF", $chars);
                $this->_raiseError('UTF-16BE encoding not supported');
                // no break
            case "\xFF":
                // UTF-16LE
                $this->_expectLiteral("\xFF\xFE", $chars);
                $this->_raiseError('UTF-16LE encoding not supported');
        }
    }

    /**
     * Parse JSON document.
     *
     * @see https://datatracker.ietf.org/doc/html/rfc7159#section-2
     *
     * @return bool False if parsing would have failed in strict mode
     */
    private function _parseDocument(\Generator $chars): bool
    {
        $ret = true;
        // whether next token must be a value or a structure
        $value_next = true;
        do {
            $this->_parseWs($chars);
            $value_next = match ($value_next) {
                true => $this->_parseValue($chars),
                false => $this->_parseCommaOrEnd($chars),
            };
            // if parsing must be stopped due to non-strict error
            if (null === $value_next) {
                $ret = false;
                break;
            }
            // no open structures remaining
            if (empty($this->structures)) {
                // whitespace is permitted after a value
                $this->_parseWs($chars);
                return $ret;
            }
        } while ($chars->valid());
        // still expecting a value
        if ($value_next) {
            $ret = false;
            if ($this->strict) {
                $this->_raiseError('Unexpected end of document.');
            }
        }
        // if there's unclosed structures
        if (!empty($this->structures)) {
            $ret = false;
            // not permitted in strict mode
            if ($this->strict) {
                $this->_raiseError('Unexpected end of document.');
            }
            // unwind structure stack
            for ($i = count($this->structures); $i > 0; --$i) {
                if (self::STRUCTURE_ARRAY === array_pop($this->structures)) {
                    $this->listener->endArray();
                } else {
                    $this->listener->endObject();
                }
            }
        }
        return $ret;
    }

    /**
     * Parse comma or end of structure.
     *
     * @return null|bool True if next token must be a value, null to stop parsing
     */
    private function _parseCommaOrEnd(\Generator $chars): ?bool
    {
        switch ($chars->current()) {
            case ',':
                $chars->next();
                $struct = $this->structures[count($this->structures) - 1];
                if (self::STRUCTURE_OBJECT === $struct) {
                    $this->_parseWs($chars);
                    $this->_parseObjectNameColon($chars);
                }
                return true;
            case '}':
                $this->_parseObjectEnd($chars);
                return false;
            case ']':
                $this->_parseArrayEnd($chars);
                return false;
            default:
                if (!$this->strict) {
                    return null;
                }
                $this->_raiseError(sprintf(
                    "Expected ',', ']' or '}', got '%s'",
                    $chars->current()
                ));
        }
    }

    /**
     * Parse value or structure.
     *
     * @return null|bool True if next token must be a value, null to stop parsing
     */
    private function _parseValue(\Generator $chars): ?bool
    {
        switch ($chars->current()) {
            case '{':
                return $this->_parseObjectStart($chars);
            case '[':
                return $this->_parseArrayStart($chars);
            case '"':
                $value = $this->_parseString($chars);
                $this->listener->value($value);
                return false;
            case 't':
                $this->_expectLiteral('true', $chars);
                $this->listener->value(true);
                return false;
            case 'f':
                $this->_expectLiteral('false', $chars);
                $this->listener->value(false);
                return false;
            case 'n':
                $this->_expectLiteral('null', $chars);
                $this->listener->value(null);
                return false;
            case null:
                if ($this->strict) {
                    $this->_raiseError('Unexpected end of document');
                }
                return null;
            default:
                $value = $this->_maybeNumber($chars);
                if (null === $value) {
                    if (!$this->strict) {
                        return null;
                    }
                    $this->_raiseError(sprintf(
                        "Expected JSON value, got '%s'",
                        $chars->current()
                    ));
                }
                $this->listener->value($value);
                return false;
        }
    }

    /**
     * Parse start of object.
     *
     * @see https://datatracker.ietf.org/doc/html/rfc7159#section-4
     *
     * @return null|bool True if next token must be a value, null to stop parsing
     */
    private function _parseObjectStart(\Generator $chars): ?bool
    {
        $this->_expectChar('{', $chars);
        $this->listener->startObject();
        $this->_incDepth();
        array_push($this->structures, self::STRUCTURE_OBJECT);
        $this->_parseWs($chars);
        // empty object
        if ('}' === $chars->current()) {
            $this->_parseObjectEnd($chars);
            return false;
        }
        // permit empty unclosed object on non-strict mode
        if (!$this->strict && '"' !== $chars->current()) {
            return null;
        }
        $this->_parseObjectNameColon($chars);
        return true;
    }

    /**
     * Parse object's name and name-separator tokens.
     */
    private function _parseObjectNameColon(\Generator $chars): void
    {
        $name = $this->_parseString($chars);
        $this->listener->name($name);
        $this->_parseWs($chars);
        $this->_expectChar(':', $chars);
    }

    /**
     * Parse end of object.
     */
    private function _parseObjectEnd(\Generator $chars): void
    {
        if (self::STRUCTURE_OBJECT !== array_pop($this->structures)) {
            $this->_raiseError('Unexpected object close');
        }
        $this->_expectChar('}', $chars);
        $this->listener->endObject();
        --$this->depth;
    }

    /**
     * Parse start of array.
     *
     * @see https://datatracker.ietf.org/doc/html/rfc7159#section-5
     *
     * @return bool True if next token must be a value
     */
    private function _parseArrayStart(\Generator $chars): bool
    {
        $this->_expectChar('[', $chars);
        $this->listener->startArray();
        $this->_incDepth();
        array_push($this->structures, self::STRUCTURE_ARRAY);
        $this->_parseWs($chars);
        // empty array
        if (']' === $chars->current()) {
            $this->_parseArrayEnd($chars);
            return false;
        }
        return true;
    }

    /**
     * Parse end of array.
     */
    private function _parseArrayEnd(\Generator $chars): void
    {
        if (self::STRUCTURE_ARRAY !== array_pop($this->structures)) {
            $this->_raiseError('Unexpected array close');
        }
        $this->_expectChar(']', $chars);
        $this->listener->endArray();
        --$this->depth;
    }

    /**
     * Parse string.
     *
     * @see https://datatracker.ietf.org/doc/html/rfc7159#section-7
     */
    private function _parseString(\Generator $chars): string
    {
        $buf = '';
        $capture = $this->_capture($chars, $buf);
        $this->_expectChar('"', $capture);
        while ($capture->valid() && '"' !== $capture->current()) {
            // escape sequence
            if ('\\' === $capture->current()) {
                $capture->next();
                $c = $capture->current();
                if (in_array($c, ['"', '\\', '/', 'b', 'f', 'n', 'r', 't'])) {
                    $capture->next();
                } elseif ('u' === $c) {
                    for ($i = 0; $i < 4; ++$i) {
                        $capture->next();
                    }
                }
            } else {
                $capture->next();
            }
        }
        $this->_expectChar('"', $capture);
        $s = json_decode($buf, flags: JSON_INVALID_UTF8_IGNORE);
        if (!is_string($s)) {
            $err = json_last_error_msg();
            $this->_raiseError("Failed to decode string. {$err}: {$buf}");
        }
        return $s;
    }

    /**
     * Try to parse number.
     *
     * If input doesn't contain a valid number, null shall be returned
     * and generator won't advance.
     *
     * @see https://datatracker.ietf.org/doc/html/rfc7159#section-6
     *
     * @return null|float|int|string Number or null
     */
    private function _maybeNumber(\Generator $chars): mixed
    {
        $buf = '';
        $capture = $this->_capture($chars, $buf);
        if ('-' === $capture->current()) {
            $capture->next();
        }
        if ('0' === $capture->current()) {
            $capture->next();
        } else {
            $this->_parseDigits($capture);
        }
        if ('' === $buf) {
            return null;
        }
        if ('.' === $capture->current()) {
            $capture->next();
            $this->_parseDigits($capture);
        }
        if (in_array($capture->current(), ['e', 'E'])) {
            $capture->next();
            if (in_array($capture->current(), ['-', '+'])) {
                $capture->next();
            }
            $this->_parseDigits($capture);
        }
        $n = json_decode($buf, flags: JSON_BIGINT_AS_STRING);
        if (null === $n) {
            $this->_raiseError('Failed to decode number');
        }
        return $n;
    }

    /**
     * Parse digits.
     */
    private function _parseDigits(\Generator $chars): void
    {
        while ($chars->valid()) {
            $n = ord($chars->current()) - ord('0');
            if ($n < 0 || $n > 9) {
                break;
            }
            $chars->next();
        }
    }

    /**
     * Wrap generator into another that captures characters.
     *
     * When yielding, previous character is added to the buffer, ie. buffer
     * doesn't contain the current character.
     *
     * @param \Generator $chars Input generator
     * @param string     $buf   Reference to string buffer
     */
    private function _capture(\Generator $chars, string &$buf): \Generator
    {
        $capture = (static function (\Generator $g) use (&$buf) {
            if (!$g->valid()) {
                return;
            }
            do {
                $c = $g->current();
                yield $c;
                // append previous character to buffer
                $buf .= $c;
                $g->next();
            } while ($g->valid());
        })($chars);
        // sync generators
        $capture->rewind();
        return $capture;
    }

    /**
     * Parse whitespace.
     */
    private function _parseWs(\Generator $chars): void
    {
        while (in_array($chars->current(), [' ', "\t", "\r", "\n"])) {
            $chars->next();
        }
    }

    /**
     * Expect given literal string from the input generator.
     *
     * @param string $str Literal string
     */
    private function _expectLiteral(string $str, \Generator $chars): void
    {
        for ($i = 0, $len = strlen($str); $i < $len; ++$i) {
            $this->_expectChar($str[$i], $chars);
        }
    }

    /**
     * Expect single character from the input generator.
     *
     * Advances to the next character.
     *
     * @param string $expect Character
     */
    private function _expectChar(string $expect, \Generator $chars): void
    {
        if ($chars->current() !== $expect) {
            if (null === $chars->current()) {
                $this->_raiseError('Unexpected end of document.');
            }
            $this->_raiseError(sprintf(
                'Expected %s, got %s',
                var_export($expect, true), var_export($chars->current(), true)
            ));
        }
        $chars->next();
    }

    /**
     * Increase nested structure depth.
     */
    private function _incDepth(): void
    {
        ++$this->depth;
        if ($this->maxDepth && $this->depth > $this->maxDepth) {
            $this->_raiseError("Maximum nesting depth {$this->maxDepth} reached");
        }
    }

    /**
     * Raise error.
     *
     * @param string $message Error message
     */
    private function _raiseError(string $message): never
    {
        throw new JSONParserException(
            "{$message} (line {$this->lineNum}, column {$this->charNum})",
            $this->lineNum, $this->charNum
        );
    }
}
