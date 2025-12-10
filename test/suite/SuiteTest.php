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
class SuiteTest extends TestCase
{
    #[DataProvider('suiteProvider')]
    public function testAll(string $path): void
    {
        $json = file_get_contents($path);
        $g = gen($json);
        $name = basename($path, '.json');
        $listener = new ConstructingJSONListener();
        $parser = new JSONGeneratorParser($listener);
        switch (substr($name, 0, 1)) {
            case 'y':
                $parser->parse($g);
                $expected = json_decode($json);
                $this->assertEqualsCanonicalizing($expected, $listener->result());
                break;
            case 'n':
                $this->expectException(Throwable::class);
                $parser->parse($g);
                break;
            case 'i':
                try {
                    $parser->parse($g);
                    $this->assertTrue(true);
                } catch (Throwable $e) {
                    $this->markTestSkipped("Optional {$name} failed");
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
}
