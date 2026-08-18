<?php

declare(strict_types=1);

namespace OpenEMR\ReleaseDocs\Tests\Shortcode;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

final class CurrentStableVersionShortcodeTest extends TestCase
{
    private const FIXTURE_DIR = __DIR__ . '/../fixtures/current-stable-version-shortcode';
    private const SHORTCODE_PATH = __DIR__ . '/../../../../layouts/shortcodes/current-stable-version.html';
    private const PARTIAL_PATH = __DIR__ . '/../../../../themes/openemr/layouts/partials/highest-final-version.html';

    private string $tmpDir = '';
    private ?string $hugoBin = null;

    protected function setUp(): void
    {
        $this->hugoBin = (new ExecutableFinder())->find('hugo');
        if ($this->hugoBin === null) {
            self::markTestSkipped('hugo is not on PATH');
        }

        $this->tmpDir = sys_get_temp_dir() . '/current-stable-version-shortcode-' . bin2hex(random_bytes(8));

        // Copy the fixture into a tmp dir so the test never mutates committed files.
        (new Process(['cp', '-R', self::FIXTURE_DIR, $this->tmpDir]))->mustRun();

        // Inject the canonical shortcode + partial under test. Both are placed
        // at site root under layouts/{shortcodes,partials}/ so the fixture
        // doesn't need to reproduce the full openemr theme.
        $shortcodeDir = $this->tmpDir . '/layouts/shortcodes';
        $partialDir = $this->tmpDir . '/layouts/partials';
        foreach ([$shortcodeDir, $partialDir] as $dir) {
            if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
                self::fail("Could not create $dir");
            }
        }
        if (!copy(self::SHORTCODE_PATH, $shortcodeDir . '/current-stable-version.html')) {
            self::fail('Could not copy shortcode into fixture');
        }
        if (!copy(self::PARTIAL_PATH, $partialDir . '/highest-final-version.html')) {
            self::fail('Could not copy partial into fixture');
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

    /**
     * Regression for the lexicographic-vs-numeric sort bug: with FINAL
     * entries 8.3.0, 8.9.0, 8.10.0 in the manifest, lexicographic sort
     * would pick 8.9.0 (because "9" > "1" at the second-component
     * character). Numeric sort must pick 8.10.0. The DRAFT 8.11.0 must
     * be ignored so an in-flight prerelease can't briefly become the
     * "current stable" between its cut and the next real release.
     */
    public function testNumericSortPicksHighestFinalAndIgnoresDraft(): void
    {
        $html = $this->readPage('probe');

        self::assertStringContainsString('current-stable-version=8.10.0', $html);
        self::assertStringNotContainsString('current-stable-version=8.9.0', $html);
        self::assertStringNotContainsString('current-stable-version=8.11.0', $html);
        self::assertStringNotContainsString('current-stable-version=8.3.0', $html);
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
