<?php

/**
 * Render the per-release acknowledgements page from `git shortlog`.
 *
 * @package   openemr/website-openemr
 * @link      https://www.open-emr.org
 * @author    Michael A. Smith <michael@opencoreemr.com>
 * @copyright Copyright (c) 2026 OpenCoreEMR Inc.
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace OpenEMR\ReleaseDocs;

use Symfony\Component\Process\Process;

final class AcknowledgementsGenerator
{
    public function generate(string $repoPath, string $fromRev, string $toRev, string $version): string
    {
        return $this->render($this->shortlog($repoPath, $fromRev, $toRev), $version);
    }

    /**
     * Tag name corresponding to a release version (e.g. "8.1.0" → "v8_1_0").
     * Matches the openemr/openemr tagging convention.
     */
    public static function tagForVersion(string $version): string
    {
        return 'v' . str_replace('.', '_', $version);
    }

    /**
     * @return list<array{name: string, commits: int}>
     */
    public function shortlog(string $repoPath, string $fromRev, string $toRev): array
    {
        $process = new Process([
            'git',
            '-C', $repoPath,
            'shortlog',
            '-sn',
            '--no-merges',
            "$fromRev..$toRev",
        ]);
        $process->mustRun();

        return $this->parseShortlog($process->getOutput());
    }

    /**
     * @return list<array{name: string, commits: int}>
     */
    public function parseShortlog(string $shortlogOutput): array
    {
        $authors = [];
        $lines = preg_split('/\R/', $shortlogOutput);
        if ($lines === false) {
            return $authors;
        }

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            $matches = [];
            if (preg_match('/^(\d+)\s+(.+)$/', $line, $matches) !== 1) {
                continue;
            }

            $authors[] = [
                'name' => $matches[2],
                'commits' => (int) $matches[1],
            ];
        }

        return $authors;
    }

    /**
     * @param list<array{name: string, commits: int}> $authors
     */
    public function render(array $authors, string $version): string
    {
        $lines = [
            '---',
            sprintf('title: "OpenEMR %s Acknowledgements"', $version),
            sprintf('version: "%s"', $version),
            '---',
            '',
            sprintf('{{< release-status version="%s" >}}', $version),
            '',
            sprintf('# OpenEMR %s — Acknowledgements', $version),
            '',
            sprintf('OpenEMR %s exists thanks to the work of the following contributors.', $version),
            sprintf('Counts reflect commits to %s on the openemr/openemr repository.', $version),
            '',
        ];

        foreach ($authors as $author) {
            $lines[] = sprintf(
                '- %s (%d commit%s)',
                $author['name'],
                $author['commits'],
                $author['commits'] === 1 ? '' : 's',
            );
        }

        return implode("\n", $lines) . "\n";
    }
}
