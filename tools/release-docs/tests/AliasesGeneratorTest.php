<?php

declare(strict_types=1);

namespace OpenEMR\ReleaseDocs\Tests;

use OpenEMR\ReleaseDocs\AliasesGenerator;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class AliasesGeneratorTest extends TestCase
{
    private string $tmpDir = '';

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/aliases-test-' . bin2hex(random_bytes(4));
        mkdir($this->tmpDir);
    }

    protected function tearDown(): void
    {
        $this->rrmdir($this->tmpDir);
    }

    public function testLoadMappingParsesYaml(): void
    {
        file_put_contents($this->tmpDir . '/aliases.yml', <<<'YAML'
            installation/8.0.0.md:
              - /wiki/index.php/OpenEMR_8.0.0_Installation
              - /wiki/index.php/Installation_8.0.0
            upgrade/8.0.0.md:
              - /wiki/index.php/OpenEMR_8.0.0_Upgrade
            YAML);

        $mapping = (new AliasesGenerator($this->tmpDir))->loadMapping($this->tmpDir . '/aliases.yml');

        self::assertSame(
            [
                'installation/8.0.0.md' => [
                    '/wiki/index.php/OpenEMR_8.0.0_Installation',
                    '/wiki/index.php/Installation_8.0.0',
                ],
                'upgrade/8.0.0.md' => [
                    '/wiki/index.php/OpenEMR_8.0.0_Upgrade',
                ],
            ],
            $mapping,
        );
    }

    public function testLintReportsMissingTargets(): void
    {
        $errors = (new AliasesGenerator($this->tmpDir))->lint([
            'missing.md' => ['/wiki/page'],
        ]);

        self::assertCount(1, $errors);
        self::assertStringContainsString('missing.md', $errors[0]);
    }

    public function testLintPassesWhenAllTargetsExist(): void
    {
        file_put_contents($this->tmpDir . '/exists.md', "---\ntitle: x\n---\n");

        $errors = (new AliasesGenerator($this->tmpDir))->lint([
            'exists.md' => ['/wiki/page'],
        ]);

        self::assertSame([], $errors);
    }

    public function testApplyAddsAliasesToFrontmatter(): void
    {
        $path = $this->tmpDir . '/page.md';
        file_put_contents($path, "---\ntitle: 'My Page'\n---\nbody\n");

        $written = (new AliasesGenerator($this->tmpDir))->apply([
            'page.md' => ['/wiki/old', '/wiki/older'],
        ]);

        self::assertSame(['page.md'], $written);
        $contents = (string) file_get_contents($path);
        self::assertStringContainsString('aliases:', $contents);
        self::assertStringContainsString('/wiki/old', $contents);
        self::assertStringContainsString('/wiki/older', $contents);
        self::assertStringContainsString("body\n", $contents);
    }

    public function testApplyMergesAndDedupesWithExistingAliases(): void
    {
        $path = $this->tmpDir . '/page.md';
        file_put_contents($path, <<<'MD'
            ---
            title: 'My Page'
            aliases:
                - /wiki/keep-me
                - /wiki/old
            ---
            body
            MD);

        (new AliasesGenerator($this->tmpDir))->apply([
            'page.md' => ['/wiki/old', '/wiki/new'],
        ]);

        $contents = (string) file_get_contents($path);
        self::assertSame(1, substr_count($contents, '/wiki/old'));
        self::assertSame(1, substr_count($contents, '/wiki/new'));
        self::assertSame(1, substr_count($contents, '/wiki/keep-me'));
        $keepPos = strpos($contents, '/wiki/keep-me');
        $newPos = strpos($contents, '/wiki/new');
        $oldPos = strpos($contents, '/wiki/old');
        self::assertNotFalse($keepPos);
        self::assertNotFalse($newPos);
        self::assertNotFalse($oldPos);
        self::assertLessThan($newPos, $keepPos);
        self::assertLessThan($oldPos, $newPos);
    }

    public function testApplyIsIdempotent(): void
    {
        $path = $this->tmpDir . '/page.md';
        file_put_contents($path, "---\ntitle: 'My Page'\n---\nbody\n");

        $generator = new AliasesGenerator($this->tmpDir);
        $generator->apply(['page.md' => ['/wiki/old']]);
        $first = (string) file_get_contents($path);

        $written = $generator->apply(['page.md' => ['/wiki/old']]);
        $second = (string) file_get_contents($path);

        self::assertSame([], $written);
        self::assertSame($first, $second);
    }

    public function testMergeAliasesRejectsFileWithoutFrontmatter(): void
    {
        $generator = new AliasesGenerator($this->tmpDir);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('frontmatter');

        $generator->mergeAliases("plain markdown\n", ['/wiki/x']);
    }

    private function rrmdir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        foreach ((array) scandir($dir) as $entry) {
            if ($entry === '.' || $entry === '..' || !is_string($entry)) {
                continue;
            }
            $path = $dir . '/' . $entry;
            if (is_dir($path)) {
                $this->rrmdir($path);
            } else {
                unlink($path);
            }
        }
        rmdir($dir);
    }
}
