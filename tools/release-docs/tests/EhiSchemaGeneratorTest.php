<?php

declare(strict_types=1);

namespace OpenEMR\ReleaseDocs\Tests;

use OpenEMR\ReleaseDocs\EhiSchemaGenerator;
use OpenEMR\ReleaseDocs\EhiSchemaSpyConfig;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class EhiSchemaGeneratorTest extends TestCase
{
    private const FIXTURE_DIR = __DIR__ . '/fixtures/ehi';

    public function testParseTablesFlattensSortsAndDeduplicates(): void
    {
        $tables = (new EhiSchemaGenerator())->parseTables(self::loadFixture('b10-tables.yml'));

        self::assertSame(['apple', 'banana', 'mango', 'zebra'], $tables);
    }

    public function testParseTablesRejectsMissingGroups(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('groups');

        (new EhiSchemaGenerator())->parseTables("tables:\n  - foo\n");
    }

    public function testParseTablesRejectsNonListGroup(): void
    {
        $this->expectException(RuntimeException::class);

        (new EhiSchemaGenerator())->parseTables("groups:\n  alpha: foo\n");
    }

    public function testParseTablesRejectsEmptyTableName(): void
    {
        $this->expectException(RuntimeException::class);

        (new EhiSchemaGenerator())->parseTables("groups:\n  alpha:\n    - ''\n");
    }

    public function testIncludeRegexBuildsAlternation(): void
    {
        $regex = (new EhiSchemaGenerator())->includeRegex(['apple', 'banana', 'mango']);

        self::assertSame('(apple|banana|mango)', $regex);
    }

    public function testIncludeRegexRejectsEmptyList(): void
    {
        $this->expectException(RuntimeException::class);

        (new EhiSchemaGenerator())->includeRegex([]);
    }

    public function testBuildCommandContainsSchemaSpyFlags(): void
    {
        $command = (new EhiSchemaGenerator())->buildCommand($this->config(), '(apple|banana)');

        self::assertContainsAdjacent($command, '-i', '(apple|banana)');
        self::assertContainsAdjacent($command, '-meta', '/checkout/schemaspy/schemas/');
        self::assertContainsAdjacent($command, '-template', '/checkout/schemaspy/layout/');
        self::assertContains('-vizjs', $command);
        self::assertContains('-norows', $command);
        self::assertContains('-noimplied', $command);
        self::assertContains('-nopages', $command);
    }

    /**
     * Assert that `$needle` appears in `$haystack` immediately followed by
     * `$value` — the argv pairing SchemaSpy expects for `-flag value`.
     *
     * @param list<string> $haystack
     */
    private static function assertContainsAdjacent(array $haystack, string $needle, string $value): void
    {
        $index = array_search($needle, $haystack, true);
        self::assertIsInt($index, "argv is missing $needle");
        self::assertSame($value, $haystack[$index + 1] ?? null, "$needle should be followed by $value");
    }

    private function config(): EhiSchemaSpyConfig
    {
        return EhiSchemaSpyConfig::fromOpenemrCheckout(
            openemrCheckout: '/checkout',
            outputDir: '/out',
            dbHost: '127.0.0.1',
            dbPort: 3306,
            dbName: 'openemr',
            dbUser: 'openemr',
            dbPassword: 'openemr',
            dbSchema: 'openemr',
            schemaspyDir: '/checkout/schemaspy',
        );
    }

    private static function loadFixture(string $name): string
    {
        $contents = file_get_contents(self::FIXTURE_DIR . '/' . $name);
        if ($contents === false) {
            throw new RuntimeException("Fixture not readable: $name");
        }

        return $contents;
    }
}
