<?php

declare(strict_types = 1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Sop\JGP\JSONGeneratorParser;
use Sop\JGP\Listener\ConstructingJSONListener;

use function Sop\JGP\TestHelpers\gen;

/**
 * @internal
 */
class NonStrictTest extends TestCase
{
    #[DataProvider('provider')]
    public function testNonStrict(string $json, mixed $expected): void
    {
        $listener = new ConstructingJSONListener();
        $parser = new JSONGeneratorParser($listener, false);
        $parser->parse(gen($json));
        $this->assertEquals($expected, $listener->result());
    }

    public static function provider(): array
    {
        return [
            ['', null],
            ['[true', [true]],
            ['[[true', [[true]]],
            ['[[[true]', [[[true]]]],
            ['[[[true]]', [[[true]]]],
            ['[', []],
            ['[[', [[]]],
            ['[[[]', [[[]]]],
            ['{', (object) []],
            ['[{', [(object) []]],
            ['{"a":true', (object) ['a' => true]],
            ['{"a":{"b":true', (object) ['a' => (object) ['b' => true]]],
            ['{"a":{"b":true}', (object) ['a' => (object) ['b' => true]]],
            ['{"a":[', (object) ['a' => []]],
            ['{"a":{"b":[', (object) ['a' => (object) ['b' => []]]],
            ['{"a":[[', (object) ['a' => [[]]]],
            ['{"a":[{', (object) ['a' => [(object) []]]],
            ['{"a":[{"b":true', (object) ['a' => [(object) ['b' => true]]]],
        ];
    }
}
