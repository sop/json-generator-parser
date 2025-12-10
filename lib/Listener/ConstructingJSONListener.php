<?php

declare(strict_types = 1);

namespace Sop\JGP\Listener;

use Sop\JGP\JSONParserListener;

/**
 * JSON generator parser listener that constructs the complete PHP type structure from JSON source.
 */
class ConstructingJSONListener extends JSONParserListener
{
    private $stack;

    private $keys;

    /**
     * Get the parsed result value.
     */
    public function result(): mixed
    {
        return count($this->stack) ? reset($this->stack) : null;
    }

    public function startDocument(): void
    {
        $this->stack = [];
        $this->keys = [];
    }

    public function startObject(): void
    {
        $this->stack[] = new \stdClass();
    }

    public function endObject(): void
    {
        $this->_pop();
    }

    public function startArray(): void
    {
        $this->stack[] = [];
    }

    public function endArray(): void
    {
        $this->_pop();
    }

    public function name(string $name): void
    {
        $this->keys[] = $name;
    }

    public function value(mixed $value): void
    {
        $this->_insert($value);
    }

    private function _pop(): void
    {
        $v = array_pop($this->stack);
        $this->_insert($v);
    }

    private function _insert(mixed $value): void
    {
        $i = count($this->stack) - 1;
        if ($i < 0) {
            $this->stack[] = $value;
        } elseif (is_array($this->stack[$i])) {
            $this->stack[$i][] = $value;
        } elseif (is_object($this->stack[$i])) {
            $k = array_pop($this->keys);
            $this->stack[$i]->{$k} = $value;
        }
    }
}
