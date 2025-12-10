<?php

declare(strict_types = 1);

namespace Sop\JGP;

/**
 * JSON generator parser listener.
 */
abstract class JSONParserListener
{
    /**
     * Called when JSON document starts.
     */
    public function startDocument(): void {}

    /**
     * Called when JSON documents ends.
     */
    public function endDocument(): void {}

    /**
     * Called when new object begins, ie. `{` token.
     */
    public function startObject(): void {}

    /**
     * Called when object ends, ie. `}` token.
     */
    public function endObject(): void {}

    /**
     * Called when new array begins, ie `[` token.
     */
    public function startArray(): void {}

    /**
     * Called when array ends. ie. `]` token.
     */
    public function endArray(): void {}

    /**
     * Called when object key (member name) is encountered.
     *
     * @param string $name Member name
     */
    public function name(string $name): void {}

    /**
     * Called when value is encountered.
     *
     * JSON values are converted to PHP types as follows:
     *  null            null
     *  true/false      boolean
     *  number          int|float|string (string for numbers larger than PHP_INT_MAX)
     *  string          string
     */
    public function value(mixed $value): void {}
}
