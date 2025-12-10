<?php

declare(strict_types = 1);

namespace Sop\JGP\Listener;

use Sop\JGP\JSONParserListener;

/**
 * JSON generator parser listener that calls a function with values and structural keys.
 *
 * Callback receives two arguments:
 *  array $keys  List of keys leading to the value
 *  mixed $value Value
 */
class CallbackJSONListener extends JSONParserListener
{
    private const TYPE_OBJECT = 1;

    private const TYPE_ARRAY = 2;

    private $keys;

    private $stack;

    private $callback;

    /**
     * Constructor.
     *
     * @param callable $callback Value callback
     */
    public function __construct(callable $callback)
    {
        $this->callback = $callback;
    }

    public function startDocument(): void
    {
        $this->keys = [];
        $this->stack = [];
    }

    public function startObject(): void
    {
        $this->stack[] = self::TYPE_OBJECT;
    }

    public function endObject(): void
    {
        $type = array_pop($this->stack);
        assert(self::TYPE_OBJECT === $type);
        $this->adjustKey();
    }

    public function startArray(): void
    {
        $this->stack[] = self::TYPE_ARRAY;
        $this->keys[] = 0;
    }

    public function endArray(): void
    {
        $type = array_pop($this->stack);
        assert(self::TYPE_ARRAY === $type);
        array_pop($this->keys);
        $this->adjustKey();
    }

    public function name(string $name): void
    {
        $this->keys[] = $name;
    }

    public function value(mixed $value): void
    {
        ($this->callback)($this->keys, $value);
        $this->adjustKey();
    }

    protected function adjustKey(): void
    {
        $key = array_pop($this->keys);
        // if in array, increment index
        if (self::TYPE_ARRAY === end($this->stack)) {
            $this->keys[] = $key + 1;
        }
    }
}
