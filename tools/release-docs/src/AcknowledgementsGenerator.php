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
    /**
     * Bare author names dropped from the acknowledgements page in addition
     * to the `[bot]`-suffix rule handled by filterAutomatedAuthors below.
     * GitHub App identities carry the `[bot]` suffix and are caught by
     * that rule; LLM assistants and IDE tools like Copilot commit under
     * a bare name with no `[bot]` marker and slip through unless listed
     * here. Add new entries as new non-human commit-author strings appear
     * in the wild.
     *
     * @var list<string>
     */
    private const NON_HUMAN_NAMES = ['Copilot'];

    public function generate(string $repoPath, string $fromRev, string $toRev, string $version): string
    {
        return $this->render($this->filterAutomatedAuthors($this->shortlog($repoPath, $fromRev, $toRev)), $version);
    }

    /**
     * Drop automated non-human authors from the acknowledgements input:
     *
     *   1. GitHub App identities, identified by the `[bot]` suffix that
     *      GitHub attaches to App accounts in commit author metadata
     *      (e.g. `dependabot[bot]`, `openemr-reserved-word-bot[bot]`).
     *   2. A hand-curated list of non-`[bot]` non-humans, held in
     *      NON_HUMAN_NAMES -- LLM assistants (Copilot) and IDE tools
     *      that commit under a bare name with no bot suffix.
     *
     * The acknowledgements page celebrates human contributors; automated
     * commit volume swamps the top of the list without carrying that
     * meaning.
     *
     * @param list<array{name: string, commits: int}> $authors
     * @return list<array{name: string, commits: int}>
     */
    public function filterAutomatedAuthors(array $authors): array
    {
        return array_values(array_filter(
            $authors,
            static fn (array $author): bool =>
                !str_ends_with($author['name'], '[bot]')
                && !in_array($author['name'], self::NON_HUMAN_NAMES, true),
        ));
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
