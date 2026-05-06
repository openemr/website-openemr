<?php

/**
 * Minimal projection of a dispatch payload onto the fields the manifest needs.
 *
 * @package   openemr/website-openemr
 * @link      https://www.open-emr.org
 * @author    Michael A. Smith <michael@opencoreemr.com>
 * @copyright Copyright (c) 2026 OpenCoreEMR Inc.
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace OpenEMR\ReleaseDocs\Manifest;

use RuntimeException;

/**
 * Decouples ReleasesManifest from the inbound JSON shape so the latter can
 * change without breaking manifest tests.
 */
final readonly class DispatchEvent
{
    public function __construct(
        public string $event,
        public string $version,
        public string $branch,
        public string $sha,
        public ?string $releasedAt,
    ) {
    }

    public static function fromJson(string $payloadJson, ?string $releasedAt = null): self
    {
        $decoded = json_decode($payloadJson, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($decoded)) {
            throw new RuntimeException('payload must decode to an object');
        }

        $event = self::requireString($decoded, 'event');
        $sha = self::requireString($decoded, 'sha');
        $data = $decoded['data'] ?? null;
        if (!is_array($data)) {
            throw new RuntimeException('payload.data must be an object');
        }
        $version = self::requireString($data, 'version');
        $branch = self::requireString($data, 'branch');

        return new self($event, $version, $branch, $sha, $releasedAt);
    }

    /**
     * @param array<int|string, mixed> $source
     */
    private static function requireString(array $source, string $key): string
    {
        $value = $source[$key] ?? null;
        if (!is_string($value) || $value === '') {
            throw new RuntimeException("payload.$key must be a non-empty string");
        }

        return $value;
    }
}
