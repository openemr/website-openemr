<?php

/**
 * @package   openemr/website-openemr
 * @link      https://www.open-emr.org
 * @author    Michael A. Smith <michael@opencoreemr.com>
 * @copyright Copyright (c) 2026 OpenCoreEMR Inc.
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace OpenEMR\ReleaseDocs\Tests\Manifest;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

/**
 * Guards the two update-manifest.php behaviours the release-docs.yml
 * workflow depends on for openemr-tag events:
 *
 * 1. When --released-at is passed, it wins (the workflow's "Resolve
 *    released_at" step is the authoritative source).
 * 2. When --released-at is NOT passed, the fallback path emits a loud
 *    stderr warning so a broken workflow-plumbing regression surfaces
 *    in the run log instead of silently producing a wrong manifest date.
 *
 * See openemr/website-openemr#145.
 */
final class UpdateManifestCliReleasedAtTest extends TestCase
{
    private const BIN = __DIR__ . '/../../bin/update-manifest.php';
    private const SCHEMA = __DIR__ . '/../../../../data/releases.schema.json';

    private string $tmpDir = '';

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/openemr-manifest-test-' . bin2hex(random_bytes(6));
        mkdir($this->tmpDir);
    }

    protected function tearDown(): void
    {
        if ($this->tmpDir === '' || !is_dir($this->tmpDir)) {
            return;
        }
        foreach (new \FilesystemIterator($this->tmpDir, \FilesystemIterator::CURRENT_AS_PATHNAME) as $path) {
            @unlink((string) $path);
        }
        @rmdir($this->tmpDir);
    }

    public function testExplicitReleasedAtWinsAndSuppressesWarning(): void
    {
        $manifest = $this->seedManifest();
        $payload = $this->writeTagPayload('8.9.0', 'v8_9_0', 'rel-890');

        $process = new Process([
            'php',
            self::BIN,
            '--payload=' . $payload,
            '--manifest=' . $manifest,
            '--schema=' . self::SCHEMA,
            '--released-at=2026-06-01',
        ]);
        $process->run();

        self::assertSame(0, $process->getExitCode(), $process->getErrorOutput());
        self::assertStringNotContainsString(
            '--released-at not passed',
            $process->getErrorOutput(),
            'the fallback warning must NOT fire when --released-at is explicitly passed',
        );

        $entries = json_decode((string) file_get_contents($manifest), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($entries);
        self::assertArrayHasKey('8.9.0', $entries);
        self::assertIsArray($entries['8.9.0']);
        self::assertSame('2026-06-01', $entries['8.9.0']['released_at'] ?? null);
    }

    public function testMissingReleasedAtWarnsLoudlyOnFallbackPath(): void
    {
        $manifest = $this->seedManifest();
        $payload = $this->writeTagPayload('8.9.0', 'v8_9_0', 'rel-890');

        $process = new Process([
            'php',
            self::BIN,
            '--payload=' . $payload,
            '--manifest=' . $manifest,
            '--schema=' . self::SCHEMA,
        ]);
        $process->run();

        // The fallback still succeeds (returns today's UTC date), but the
        // stderr warning must be visible so a workflow-plumbing regression
        // -- e.g. the "Resolve released_at" step stopping firing -- doesn't
        // silently corrupt future manifest entries.
        self::assertSame(0, $process->getExitCode(), $process->getErrorOutput());
        self::assertStringContainsString(
            '--released-at not passed for openemr-tag event',
            $process->getErrorOutput(),
        );
        self::assertStringContainsString(
            "falling back to today's UTC date",
            $process->getErrorOutput(),
        );
    }

    private function seedManifest(): string
    {
        $path = $this->tmpDir . '/releases.json';
        file_put_contents($path, json_encode(new \stdClass(), JSON_PRETTY_PRINT) . "\n");
        return $path;
    }

    private function writeTagPayload(string $version, string $tag, string $branch): string
    {
        $envelope = [
            'event' => 'openemr-tag',
            'repo' => 'openemr/openemr',
            'sha' => str_repeat('a', 40),
            'actor' => 'openemr-release-bot',
            'dispatched_at' => '2026-06-04T12:00:00Z',
            'data' => [
                'tag' => $tag,
                'branch' => $branch,
                'version' => $version,
                'prev_release' => '8.0.0',
            ],
        ];
        $path = $this->tmpDir . '/payload.json';
        file_put_contents($path, json_encode($envelope, JSON_PRETTY_PRINT));
        return $path;
    }
}
