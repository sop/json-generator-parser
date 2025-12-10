<?php

declare(strict_types = 1);

namespace Sop\JGP\TestHelpers;

function gen(string $s): \Generator
{
    foreach (str_split($s, 4) as $chunk) {
        yield $chunk;
    }
}
