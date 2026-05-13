<?php

/**
 * Read, mutate, and validate the data/releases.json manifest.
 *
 * @package   openemr/website-openemr
 * @link      https://www.open-emr.org
 * @author    Michael A. Smith <michael@opencoreemr.com>
 * @copyright Copyright (c) 2026 OpenCoreEMR Inc.
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace OpenEMR\ReleaseDocs\Manifest;

use JsonException;
use Opis\JsonSchema\Errors\ErrorFormatter;
use Opis\JsonSchema\Errors\ValidationError;
use Opis\JsonSchema\Validator;
use RuntimeException;

/**
 * Mutates data/releases.json in response to dispatch events. The shape it
 * writes is governed by data/releases.schema.json — every mutation re-validates
 * the result so we never write a manifest the shortcode can't read.
 */
final class ReleasesManifest
{
    public function __construct(
        private readonly string $manifestPath,
        private readonly string $schemaPath,
    ) {
    }

    /**
     * Apply a dispatch event to the manifest and persist the result.
     *
     * - openemr-rel-cut:    insert/replace the entry as DRAFT (released_at null).
     * - openemr-rel-update: refresh the sha on the existing DRAFT entry.
     * - openemr-tag:        flip status to FINAL and set released_at.
     */
    public function apply(DispatchEvent $event): void
    {
        $manifest = $this->load();

        $manifest = match ($event->event) {
            'openemr-rel-cut' => $this->applyRelCut($manifest, $event),
            'openemr-rel-update' => $this->applyRelUpdate($manifest, $event),
            'openemr-tag' => $this->applyTag($manifest, $event),
            default => throw new RuntimeException("unsupported event: $event->event"),
        };

        $this->validate($manifest);
        $this->save($manifest);
    }

    /**
     * @param array<string, array<string, string|null|array<string, string>>> $manifest
     * @return array<string, array<string, string|null|array<string, string>>>
     */
    private function applyRelCut(array $manifest, DispatchEvent $event): array
    {
        $manifest[$event->version] = [
            'status' => 'DRAFT',
            'branch' => $event->branch,
            'sha' => $event->sha,
            'released_at' => null,
        ];

        return $manifest;
    }

    /**
     * @param array<string, array<string, string|null|array<string, string>>> $manifest
     * @return array<string, array<string, string|null|array<string, string>>>
     */
    private function applyRelUpdate(array $manifest, DispatchEvent $event): array
    {
        $existing = $manifest[$event->version] ?? null;
        if ($existing === null) {
            // First time we've seen this version; treat update as cut.
            return $this->applyRelCut($manifest, $event);
        }

        $existing['branch'] = $event->branch;
        $existing['sha'] = $event->sha;
        $manifest[$event->version] = $existing;

        return $manifest;
    }

    /**
     * @param array<string, array<string, string|null|array<string, string>>> $manifest
     * @return array<string, array<string, string|null|array<string, string>>>
     */
    private function applyTag(array $manifest, DispatchEvent $event): array
    {
        if ($event->releasedAt === null) {
            throw new RuntimeException('openemr-tag requires released_at');
        }

        $existing = $manifest[$event->version] ?? [
            'branch' => $event->branch,
            'sha' => $event->sha,
        ];
        $existing['status'] = 'FINAL';
        $existing['branch'] = $event->branch;
        $existing['sha'] = $event->sha;
        $existing['released_at'] = $event->releasedAt;
        $manifest[$event->version] = $existing;

        return $manifest;
    }

    /**
     * @return array<string, array<string, string|null|array<string, string>>>
     */
    private function load(): array
    {
        if (!file_exists($this->manifestPath)) {
            return [];
        }

        $contents = @file_get_contents($this->manifestPath);
        if ($contents === false) {
            throw new RuntimeException("manifest not readable: $this->manifestPath");
        }

        try {
            $decoded = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new RuntimeException('manifest is not valid JSON', 0, $e);
        }

        if (!is_array($decoded)) {
            throw new RuntimeException('manifest root must be an object');
        }

        $normalized = [];
        foreach ($decoded as $version => $entry) {
            if (!is_string($version) || !is_array($entry)) {
                throw new RuntimeException('manifest entry must be a string-keyed object');
            }
            $normalized[$version] = $this->normalizeEntry($entry);
        }

        return $normalized;
    }

    /**
     * @param array<int|string, mixed> $entry
     * @return array<string, string|null|array<string, string>>
     */
    private function normalizeEntry(array $entry): array
    {
        $result = [];
        foreach ($entry as $key => $value) {
            if (!is_string($key)) {
                throw new RuntimeException('manifest entry keys must be strings');
            }
            if ($value === null || is_string($value)) {
                $result[$key] = $value;
                continue;
            }
            if (is_array($value)) {
                $result[$key] = $this->normalizeStringMap($key, $value);
                continue;
            }
            throw new RuntimeException("manifest entry $key must be string, null, or string-keyed string map");
        }

        return $result;
    }

    /**
     * @param array<int|string, mixed> $value
     * @return array<string, string>
     */
    private function normalizeStringMap(string $parentKey, array $value): array
    {
        $sub = [];
        foreach ($value as $k => $v) {
            if (!is_string($k) || !is_string($v)) {
                throw new RuntimeException("manifest entry $parentKey.$k must be string");
            }
            $sub[$k] = $v;
        }
        return $sub;
    }

    /**
     * @param array<string, array<string, string|null|array<string, string>>> $manifest
     */
    private function validate(array $manifest): void
    {
        $schemaContents = @file_get_contents($this->schemaPath);
        if ($schemaContents === false) {
            throw new RuntimeException("schema not readable: $this->schemaPath");
        }

        $schema = json_decode($schemaContents, false, 512, JSON_THROW_ON_ERROR);
        if (!is_object($schema)) {
            throw new RuntimeException('schema root must decode to an object');
        }

        $payload = json_decode(json_encode($manifest, JSON_THROW_ON_ERROR), false, 512, JSON_THROW_ON_ERROR);

        $validator = new Validator();
        $validator->setMaxErrors(20);
        $result = $validator->validate($payload, $schema);

        if (!$result->hasError()) {
            return;
        }

        $error = $result->error();
        $formatted = $error instanceof ValidationError
            ? json_encode((new ErrorFormatter())->format($error), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
            : 'unknown';

        throw new RuntimeException("manifest failed schema validation: $formatted");
    }

    /**
     * @param array<string, array<string, string|null|array<string, string>>> $manifest
     */
    private function save(array $manifest): void
    {
        ksort($manifest);
        $encoded = json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $written = @file_put_contents($this->manifestPath, $encoded . "\n");
        if ($written === false) {
            throw new RuntimeException("could not write manifest: $this->manifestPath");
        }
    }
}
