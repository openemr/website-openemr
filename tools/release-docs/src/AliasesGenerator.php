<?php

/**
 * Apply or lint Hugo aliases from a wiki-URL → page mapping.
 *
 * @package   openemr/website-openemr
 * @link      https://www.open-emr.org
 * @author    Michael A. Smith <michael@opencoreemr.com>
 * @copyright Copyright (c) 2026 OpenCoreEMR Inc.
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace OpenEMR\ReleaseDocs;

use RuntimeException;
use Symfony\Component\Yaml\Yaml;

final class AliasesGenerator
{
    public function __construct(
        private readonly string $contentRoot,
    ) {
    }

    /**
     * @return array<string, list<string>>
     *     Map of content-relative target path → list of alias URL paths.
     */
    public function loadMapping(string $yamlPath): array
    {
        $contents = @file_get_contents($yamlPath);
        if ($contents === false) {
            throw new RuntimeException("Mapping not readable: $yamlPath");
        }

        $parsed = Yaml::parse($contents);
        if (!is_array($parsed)) {
            throw new RuntimeException("Mapping at $yamlPath is not a YAML mapping");
        }

        $result = [];
        foreach ($parsed as $target => $aliases) {
            if (!is_string($target) || $target === '') {
                throw new RuntimeException('Mapping target keys must be non-empty strings');
            }
            if (!is_array($aliases)) {
                throw new RuntimeException("Aliases for $target must be a list");
            }
            $list = [];
            foreach ($aliases as $alias) {
                if (!is_string($alias) || $alias === '') {
                    throw new RuntimeException("Aliases for $target must be non-empty strings");
                }
                $list[] = $alias;
            }
            $result[$target] = $list;
        }

        return $result;
    }

    /**
     * @param array<string, list<string>> $mapping
     * @return list<string> Error messages, empty when the mapping is valid.
     */
    public function lint(array $mapping): array
    {
        $errors = [];
        foreach ($mapping as $target => $_) {
            $path = $this->contentRoot . '/' . $target;
            if (!is_file($path)) {
                $errors[] = "Target does not exist: $target";
            }
        }

        return $errors;
    }

    /**
     * @param array<string, list<string>> $mapping
     * @return list<string> Paths (content-relative) that were written.
     */
    public function apply(array $mapping): array
    {
        $written = [];
        foreach ($mapping as $target => $aliases) {
            $path = $this->contentRoot . '/' . $target;
            $contents = @file_get_contents($path);
            if ($contents === false) {
                throw new RuntimeException("Target does not exist: $target");
            }
            $updated = $this->mergeAliases($contents, $aliases);
            if ($updated === $contents) {
                continue;
            }
            if (file_put_contents($path, $updated) === false) {
                throw new RuntimeException("Could not write $path");
            }
            $written[] = $target;
        }

        return $written;
    }

    /**
     * @param list<string> $aliases
     */
    public function mergeAliases(string $fileContents, array $aliases): string
    {
        if (!str_starts_with($fileContents, "---\n")) {
            throw new RuntimeException('File does not start with a YAML frontmatter block');
        }
        $end = strpos($fileContents, "\n---\n", 4);
        if ($end === false) {
            throw new RuntimeException('File frontmatter has no closing delimiter');
        }
        $frontmatterText = substr($fileContents, 4, $end - 4);
        $body = substr($fileContents, $end + 5);

        $frontmatter = Yaml::parse($frontmatterText);
        if (!is_array($frontmatter)) {
            throw new RuntimeException('Frontmatter is not a YAML mapping');
        }

        $existing = [];
        if (isset($frontmatter['aliases']) && is_array($frontmatter['aliases'])) {
            foreach ($frontmatter['aliases'] as $existingAlias) {
                if (is_string($existingAlias) && $existingAlias !== '') {
                    $existing[] = $existingAlias;
                }
            }
        }
        $merged = array_values(array_unique(array_merge($existing, $aliases)));
        sort($merged);
        $frontmatter['aliases'] = $merged;

        $rendered = Yaml::dump($frontmatter, 4, 2);

        return "---\n" . $rendered . "---\n" . $body;
    }
}
