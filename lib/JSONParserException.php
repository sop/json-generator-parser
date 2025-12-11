<?php

declare(strict_types = 1);

namespace Sop\JGP;

class JSONParserException extends \RuntimeException
{
    public function __construct(
        string $msg,
        private readonly int $sourceLine,
        private readonly int $sourceColumn
    ) {
        parent::__construct($msg);
    }
}
