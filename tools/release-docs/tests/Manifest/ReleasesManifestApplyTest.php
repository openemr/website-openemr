<?php

declare(strict_types=1);

namespace OpenEMR\ReleaseDocs\Tests\Manifest;

use OpenEMR\ReleaseDocs\Manifest\DispatchEvent;
use OpenEMR\ReleaseDocs\Manifest\ReleasesManifest;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ReleasesManifestApplyTest extends TestCase
{
    private const SCHEMA_PATH = __DIR__ . '/../../../../data/releases.schema.json';

    private string $manifestPath = '';

    protected function setUp(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'releases-manifest-');
        if ($tmp === false) {
            throw new RuntimeException('could not allocate temp manifest path');
        }
        $this->manifestPath = $tmp;
        // Start each test with no manifest on disk; specific tests seed as needed.
        unlink($this->manifestPath);
    }

    protected function tearDown(): void
    {
        if ($this->manifestPath !== '' && file_exists($this->manifestPath)) {
            unlink($this->manifestPath);
        }
    }

    public function testRelCutInsertsDraftEntry(): void
    {
        $event = new DispatchEvent(
            event: 'openemr-rel-cut',
            version: '8.1.0',
            branch: 'rel-810',
            sha: 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
            releasedAt: null,
        );

        $this->manifest()->apply($event);

        self::assertSame(
            [
                '8.1.0' => [
                    'status' => 'DRAFT',
                    'branch' => 'rel-810',
                    'sha' => 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
                    'released_at' => null,
                ],
            ],
            $this->readManifest(),
        );
    }

    public function testRelUpdateRefreshesShaWithoutTouchingStatus(): void
    {
        $this->seed([
            '8.1.0' => [
                'status' => 'DRAFT',
                'branch' => 'rel-810',
                'sha' => 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
                'released_at' => null,
            ],
        ]);

        $event = new DispatchEvent(
            event: 'openemr-rel-update',
            version: '8.1.0',
            branch: 'rel-810',
            sha: 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb',
            releasedAt: null,
        );

        $this->manifest()->apply($event);

        $entry = $this->readManifest()['8.1.0'];
        self::assertSame('DRAFT', $entry['status']);
        self::assertSame('bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb', $entry['sha']);
        self::assertNull($entry['released_at']);
    }

    public function testTagFlipsStatusToFinalAndStampsReleasedAt(): void
    {
        $this->seed([
            '8.1.0' => [
                'status' => 'DRAFT',
                'branch' => 'rel-810',
                'sha' => 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
                'released_at' => null,
            ],
        ]);

        $event = new DispatchEvent(
            event: 'openemr-tag',
            version: '8.1.0',
            branch: 'rel-810',
            sha: 'cccccccccccccccccccccccccccccccccccccccc',
            releasedAt: '2026-05-01',
        );

        $this->manifest()->apply($event);

        self::assertSame(
            [
                'status' => 'FINAL',
                'branch' => 'rel-810',
                'sha' => 'cccccccccccccccccccccccccccccccccccccccc',
                'released_at' => '2026-05-01',
            ],
            $this->readManifest()['8.1.0'],
        );
    }

    public function testRelUpdateFallsBackToInsertWhenVersionAbsent(): void
    {
        $event = new DispatchEvent(
            event: 'openemr-rel-update',
            version: '8.2.0',
            branch: 'rel-820',
            sha: 'dddddddddddddddddddddddddddddddddddddddd',
            releasedAt: null,
        );

        $this->manifest()->apply($event);

        self::assertSame(
            [
                'status' => 'DRAFT',
                'branch' => 'rel-820',
                'sha' => 'dddddddddddddddddddddddddddddddddddddddd',
                'released_at' => null,
            ],
            $this->readManifest()['8.2.0'],
        );
    }

    public function testTagWithoutReleasedAtFails(): void
    {
        $event = new DispatchEvent(
            event: 'openemr-tag',
            version: '8.1.0',
            branch: 'rel-810',
            sha: 'eeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeee',
            releasedAt: null,
        );

        $this->expectException(RuntimeException::class);
        $this->manifest()->apply($event);
    }

    public function testManifestPersistsSortedAndValidates(): void
    {
        $this->seed([
            '8.0.0' => [
                'status' => 'FINAL',
                'branch' => 'rel-800',
                'sha' => 'b91b73600327acb46252b9fce7d04467eea126fd',
                'released_at' => '2026-02-13',
            ],
        ]);

        $this->manifest()->apply(new DispatchEvent(
            event: 'openemr-rel-cut',
            version: '8.2.0',
            branch: 'rel-820',
            sha: 'ffffffffffffffffffffffffffffffffffffffff',
            releasedAt: null,
        ));

        $manifest = $this->readManifest();
        self::assertSame(['8.0.0', '8.2.0'], array_keys($manifest));
    }

    public function testHistoricalEntryPreservedAcrossDispatch(): void
    {
        $this->seed([
            '5.0.2' => [
                'status' => 'FINAL',
                'released_at' => '2018-08-23',
                'archive_urls' => [
                    'tarball' => 'https://sourceforge.net/projects/openemr/files/'
                        . 'OpenEMR%20Current/5.0.2/openemr-5.0.2.tar.gz/download',
                    'zip' => 'https://sourceforge.net/projects/openemr/files/'
                        . 'OpenEMR%20Current/5.0.2/openemr-5.0.2.zip/download',
                ],
            ],
            '8.0.0' => [
                'status' => 'FINAL',
                'branch' => 'rel-800',
                'sha' => 'b91b73600327acb46252b9fce7d04467eea126fd',
                'released_at' => '2026-02-13',
            ],
        ]);

        $this->manifest()->apply(new DispatchEvent(
            event: 'openemr-rel-cut',
            version: '8.1.0',
            branch: 'rel-810',
            sha: 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
            releasedAt: null,
        ));

        $manifest = $this->readManifest();
        self::assertSame(['5.0.2', '8.0.0', '8.1.0'], array_keys($manifest));
        self::assertSame(
            [
                'status' => 'FINAL',
                'released_at' => '2018-08-23',
                'archive_urls' => [
                    'tarball' => 'https://sourceforge.net/projects/openemr/files/'
                        . 'OpenEMR%20Current/5.0.2/openemr-5.0.2.tar.gz/download',
                    'zip' => 'https://sourceforge.net/projects/openemr/files/'
                        . 'OpenEMR%20Current/5.0.2/openemr-5.0.2.zip/download',
                ],
            ],
            $manifest['5.0.2'],
        );
    }

    public function testEntryWithNestedCompatibilityAndDownloadsLoads(): void
    {
        $this->seed([
            '8.0.0' => [
                'status' => 'FINAL',
                'branch' => 'rel-800',
                'sha' => 'b91b73600327acb46252b9fce7d04467eea126fd',
                'released_at' => '2026-02-13',
                'compatibility' => [
                    'php' => ['min' => '8.2', 'max' => '8.5'],
                    'mariadb' => ['min' => '10.6', 'max' => '11.8'],
                    'recommended_db' => 'MariaDB',
                ],
                'downloads' => [
                    'docker' => ['install_url' => 'https://example.invalid/docker'],
                ],
            ],
        ]);

        $this->manifest()->apply(new DispatchEvent(
            event: 'openemr-rel-cut',
            version: '8.1.0',
            branch: 'rel-810',
            sha: 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
            releasedAt: null,
        ));

        $manifest = $this->readManifest();
        self::assertSame(
            [
                'status' => 'FINAL',
                'branch' => 'rel-800',
                'sha' => 'b91b73600327acb46252b9fce7d04467eea126fd',
                'released_at' => '2026-02-13',
                'compatibility' => [
                    'php' => ['min' => '8.2', 'max' => '8.5'],
                    'mariadb' => ['min' => '10.6', 'max' => '11.8'],
                    'recommended_db' => 'MariaDB',
                ],
                'downloads' => [
                    'docker' => ['install_url' => 'https://example.invalid/docker'],
                ],
            ],
            $manifest['8.0.0'],
        );
    }

    public function testFromJsonExtractsRequiredFields(): void
    {
        $payload = json_encode([
            'event' => 'openemr-rel-cut',
            'repo' => 'openemr/openemr',
            'sha' => '0123456789abcdef0123456789abcdef01234567',
            'actor' => 'openemr-release-bot[bot]',
            'dispatched_at' => '2026-04-29T12:34:56Z',
            'data' => [
                'branch' => 'rel-810',
                'version' => '8.1.0',
                'prev_release' => '8.0.0',
            ],
        ], JSON_THROW_ON_ERROR);

        $event = DispatchEvent::fromJson($payload);

        self::assertSame('openemr-rel-cut', $event->event);
        self::assertSame('8.1.0', $event->version);
        self::assertSame('rel-810', $event->branch);
        self::assertSame('0123456789abcdef0123456789abcdef01234567', $event->sha);
        self::assertNull($event->releasedAt);
    }

    private function manifest(): ReleasesManifest
    {
        return new ReleasesManifest($this->manifestPath, self::SCHEMA_PATH);
    }

    /**
     * @param array<string, array<string, mixed>> $contents
     */
    private function seed(array $contents): void
    {
        $encoded = json_encode($contents, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        file_put_contents($this->manifestPath, $encoded . "\n");
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function readManifest(): array
    {
        $contents = file_get_contents($this->manifestPath);
        if ($contents === false) {
            throw new RuntimeException('manifest not readable in test');
        }
        $decoded = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($decoded)) {
            throw new RuntimeException('manifest decoded to non-array in test');
        }

        /** @var array<string, array<string, mixed>> */
        return $decoded;
    }
}
