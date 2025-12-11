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
class EnclosedTest extends TestCase
{
    #[DataProvider('provider')]
    public function testNonStrict(string $json, mixed $expected): void
    {
        $listener = new ConstructingJSONListener();
        $parser = new JSONGeneratorParser($listener, false);
        $parser->parse(gen($json), true);
        $this->assertEquals($expected, $listener->result());
    }

    public static function provider(): array
    {
        return [
            [<<<'JSON'
Sure, here's your stuff.
```json
{
    "a" : true
}
```
Is there anything else i can do for you?
JSON, (object) ['a' => true]],
            [<<<'JSON'
Header
{
    "a" : true
}
JSON, (object) ['a' => true]],
            [<<<'JSON'
{
    "a" : true
}
Trailer
JSON, (object) ['a' => true]],
            ['{"a":true}', (object) ['a' => true]],
            [<<<'JSON'
```json
{
    "a" : true
```
JSON, (object) ['a' => true]],
            [<<<'JSON'
```json
[[1
```
JSON, [[1]]],
            [<<<'JSON'
```json
[[1]
```
JSON, [[1]]],
            [<<<'JSON'
```json
[
    {
        "a" : true,
        "b" : [
```
JSON, [(object) ['a' => true, 'b' => []]]],
        ];
    }
}
