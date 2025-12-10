<?php

declare(strict_types = 1);

use PHPUnit\Framework\TestCase;
use Sop\JGP\JSONGeneratorParser;
use Sop\JGP\Listener\ConstructingJSONListener;

use function Sop\JGP\TestHelpers\gen;

/**
 * @internal
 */
class MiscTest extends TestCase
{
    public function testHugeNested(): void
    {
        $count = 1024 * 8;
        $json = str_repeat('[', $count) . str_repeat(']', $count);
        $expected = [];
        $ref = &$expected;
        for ($i = 1; $i < $count; ++$i) {
            $ref[] = [];
            $ref = &$ref[0];
        }
        $listener = new ConstructingJSONListener();
        $parser = new JSONGeneratorParser($listener, maxDepth: 0);
        $parser->parse(gen($json));
        $this->assertEquals($expected, $listener->result());
    }
}
