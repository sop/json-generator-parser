<?php

declare(strict_types = 1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Sop\JGP\JSONGeneratorParser;
use Sop\JGP\Listener\SimpleJSONListener;

/**
 * @internal
 */
class SuiteTest extends TestCase
{
    #[DataProvider('suiteProvider')]
    public function testAll(string $path): void
    {
        $g = (function ($json) {
            foreach (str_split($json, 10) as $chunk) {
                yield $chunk;
            }
        })(file_get_contents($path));
        $name = basename($path, '.json');
        switch (substr($name, 0, 1)) {
            case 'y':
                $this->assertTrue(self::parse($g), "{$name} must succeed");
                break;
            case 'n':
                $this->assertFalse(self::parse($g), "{$name} must fail");
                break;
            case 'i':
                if (self::parse($g)) {
                    $this->assertTrue(true);
                } else {
                    $this->markTestSkipped("{$name} rejected but is optional");
                }
                break;
        }
    }

    public static function suiteProvider(): array
    {
        $tests_dir = TEST_ASSETS_DIR . '/JSONTestSuite/test_parsing';
        return array_map(
            fn (string $file) => ["{$tests_dir}/{$file}"],
            array_filter(
                scandir($tests_dir),
                fn (string $file) => !str_starts_with($file, '.')
            )
        );
    }

    private static function parse(Generator $g): bool
    {
        $listener = new SimpleJSONListener(function (array $keys, mixed $value) {});
        $parser = new JSONGeneratorParser($listener);
        try {
            $parser->parse($g);
        } catch (Throwable $e) {
            return false;
        }
        return true;
    }
}
