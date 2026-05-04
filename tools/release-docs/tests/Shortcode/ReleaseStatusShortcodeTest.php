<?php

declare(strict_types=1);

namespace OpenEMR\ReleaseDocs\Tests\Shortcode;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

final class ReleaseStatusShortcodeTest extends TestCase
{
    private const FIXTURE_DIR = __DIR__ . '/../fixtures/release-status-shortcode';
    private const SHORTCODE_PATH = __DIR__ . '/../../../../layouts/shortcodes/release-status.html';

    private string $tmpDir = '';
    private ?string $hugoBin = null;

    protected function setUp(): void
    {
        $this->hugoBin = (new ExecutableFinder())->find('hugo');
        if ($this->hugoBin === null) {
            self::markTestSkipped('hugo is not on PATH');
        }

        $this->tmpDir = sys_get_temp_dir() . '/release-status-shortcode-' . bin2hex(random_bytes(8));

        // Copy the fixture into a tmp dir so the test never mutates committed files.
        (new Process(['cp', '-R', self::FIXTURE_DIR, $this->tmpDir]))->mustRun();

        // Inject the canonical shortcode under test.
        $shortcodeDir = $this->tmpDir . '/layouts/shortcodes';
        if (!is_dir($shortcodeDir) && !mkdir($shortcodeDir, 0755, true)) {
            self::fail("Could not create $shortcodeDir");
        }
        if (!copy(self::SHORTCODE_PATH, $shortcodeDir . '/release-status.html')) {
            self::fail('Could not copy shortcode into fixture');
        }

        // Render the site once per test.
        $build = new Process([
            $this->hugoBin,
            '--source', $this->tmpDir,
            '--destination', $this->tmpDir . '/public',
            '--quiet',
        ]);
        $build->mustRun();
    }

    protected function tearDown(): void
    {
        if ($this->tmpDir !== '') {
            (new Process(['rm', '-rf', $this->tmpDir]))->mustRun();
        }
    }

    public function testDraftRendersBranchAndShortSha(): void
    {
        $html = $this->readPage('draft');

        self::assertStringContainsString('release-status-draft', $html);
        self::assertStringContainsString('DRAFT', $html);
        self::assertStringContainsString('rel-810', $html);
        self::assertStringContainsString('abc1234', $html);
    }

    public function testFinalRendersReleaseDate(): void
    {
        $html = $this->readPage('final');

        self::assertStringContainsString('release-status-final', $html);
        self::assertStringContainsString('FINAL', $html);
        self::assertStringContainsString('8.0.0', $html);
        self::assertStringContainsString('2025-09-01', $html);
    }

    public function testUnknownVersionRendersUnknownState(): void
    {
        $html = $this->readPage('unknown');

        self::assertStringContainsString('release-status-unknown', $html);
        self::assertStringContainsString('Unknown release version', $html);
        self::assertStringContainsString('9.9.9', $html);
    }

    private function readPage(string $slug): string
    {
        $path = $this->tmpDir . "/public/$slug/index.html";
        $contents = file_get_contents($path);
        if ($contents === false) {
            self::fail("Could not read rendered page: $path");
        }

        return $contents;
    }
}
