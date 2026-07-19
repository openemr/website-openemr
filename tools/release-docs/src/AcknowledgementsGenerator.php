<?php

/**
 * Render the per-release acknowledgements page from `git log`.
 *
 * Contributors are grouped by author email (a stable per-person
 * identity) rather than by name string: any variation in spelling
 * or capitalization would otherwise split one person into multiple
 * rows -- e.g. "steve waite" / "Stephen Waite" / "stephen waite"
 * were all showing up as separate entries for the same person in
 * 8.1.0's page. See openemr/website-openemr#135.
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
        $commits = $this->logAuthors($repoPath, $fromRev, $toRev);
        $grouped = $this->groupByEmail($commits);
        $merged = $this->mergeSameNameEntries($grouped);
        return $this->render($this->filterAutomatedAuthors($merged), $version);
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
            static fn(array $author): bool =>
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
     * One record per commit in the range, carrying the author's email
     * (lowercased-later during grouping to give per-person identity)
     * and the display name they used on that commit.
     *
     * @return list<array{email: string, name: string}>
     */
    public function logAuthors(string $repoPath, string $fromRev, string $toRev): array
    {
        // %aE gives the author email; %aN respects .mailmap so upstream
        // canonicalization (if any) is honored before we group. Tab
        // separator picked because commit author names/emails can't
        // contain tabs.
        $process = new Process([
            'git',
            '-C', $repoPath,
            'log',
            '--no-merges',
            '--format=%aE%x09%aN',
            "$fromRev..$toRev",
        ]);
        $process->mustRun();

        return $this->parseLogOutput($process->getOutput());
    }

    /**
     * @return list<array{email: string, name: string}>
     */
    public function parseLogOutput(string $logOutput): array
    {
        $commits = [];
        $lines = preg_split('/\R/', $logOutput);
        if ($lines === false) {
            return $commits;
        }

        foreach ($lines as $line) {
            if ($line === '') {
                continue;
            }

            $parts = explode("\t", $line, 2);
            if (count($parts) !== 2) {
                continue;
            }
            $email = trim($parts[0]);
            $name = trim($parts[1]);
            if ($email === '' || $name === '') {
                continue;
            }

            $commits[] = ['email' => $email, 'name' => $name];
        }

        return $commits;
    }

    /**
     * Group per-commit records by lowercased author email, sum commit
     * counts across all name spellings tied to that email, and pick a
     * canonical display name per email.
     *
     * Display-name selection per email, tie-breaking in order:
     *   1. Most commits under that spelling (the maintainer's own
     *      most-frequent spelling of themselves is a good default).
     *   2. Longest name (a fully-qualified "Firstname Lastname" beats
     *      a shorter nickname or bare email).
     *   3. Alphabetical ascending (final deterministic tie-break).
     *
     * Final list sorted by total commits descending, then display name
     * ascending. Same {name, commits} shape as the pre-fix output so
     * `render()` + `filterAutomatedAuthors()` don't need to change.
     *
     * @param list<array{email: string, name: string}> $commits
     * @return list<array{name: string, commits: int}>
     */
    public function groupByEmail(array $commits): array
    {
        /** @var array<string, array{total: int, names: array<string, int>}> $byEmail */
        $byEmail = [];
        foreach ($commits as $commit) {
            $key = strtolower($commit['email']);
            if (!isset($byEmail[$key])) {
                $byEmail[$key] = ['total' => 0, 'names' => []];
            }
            $byEmail[$key]['total']++;
            $byEmail[$key]['names'][$commit['name']]
                = ($byEmail[$key]['names'][$commit['name']] ?? 0) + 1;
        }

        $authors = [];
        foreach ($byEmail as $entry) {
            $authors[] = [
                'name' => self::pickDisplayName($entry['names']),
                'commits' => $entry['total'],
            ];
        }

        usort($authors, static function (array $a, array $b): int {
            $byCommits = $b['commits'] <=> $a['commits'];
            return $byCommits !== 0 ? $byCommits : strcmp($a['name'], $b['name']);
        });

        return $authors;
    }

    /**
     * Second-pass dedup after groupByEmail(): merge entries whose
     * resolved display name matches case-insensitively. This catches
     * the same-person-different-email case (a contributor committing
     * from both work and personal git accounts under the same display
     * name) that pure email-based grouping would leave as two rows.
     *
     * Uses mb_strtolower(..., 'UTF-8') for the name key so accented
     * characters (é/É, ß/SS, etc.) fold correctly; CJK characters
     * have no case and pass through unchanged.
     *
     * @param list<array{name: string, commits: int}> $entries
     * @return list<array{name: string, commits: int}>
     */
    public function mergeSameNameEntries(array $entries): array
    {
        /** @var array<string, array{total: int, spellings: array<string, int>}> $byName */
        $byName = [];
        foreach ($entries as $entry) {
            $key = mb_strtolower($entry['name'], 'UTF-8');
            if (!isset($byName[$key])) {
                $byName[$key] = ['total' => 0, 'spellings' => []];
            }
            $byName[$key]['total'] += $entry['commits'];
            // Preserve each spelling weighted by its commit contribution so
            // the same tie-break rules (most-used, then longest, then
            // alphabetical) apply to the merged display name.
            $byName[$key]['spellings'][$entry['name']]
                = ($byName[$key]['spellings'][$entry['name']] ?? 0) + $entry['commits'];
        }

        $merged = [];
        foreach ($byName as $entry) {
            $merged[] = [
                'name' => self::pickDisplayName($entry['spellings']),
                'commits' => $entry['total'],
            ];
        }

        usort($merged, static function (array $a, array $b): int {
            $byCommits = $b['commits'] <=> $a['commits'];
            return $byCommits !== 0 ? $byCommits : strcmp($a['name'], $b['name']);
        });

        return $merged;
    }

    /**
     * @param array<string, int> $names
     */
    private static function pickDisplayName(array $names): string
    {
        $best = '';
        $bestCount = -1;
        foreach ($names as $name => $count) {
            if ($count > $bestCount) {
                $best = $name;
                $bestCount = $count;
                continue;
            }
            if ($count < $bestCount) {
                continue;
            }
            // Tie on count: prefer longest, then alphabetical.
            if (
                strlen($name) > strlen($best)
                || (strlen($name) === strlen($best) && strcmp($name, $best) < 0)
            ) {
                $best = $name;
            }
        }
        return $best;
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
