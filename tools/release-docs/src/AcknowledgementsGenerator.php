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
     * here. `openemr-release-bot` (no `[bot]`) also appears as a
     * Co-authored-by trailer value on some conductor-generated commits
     * with a non-noreply email (`release-bot@openemr.invalid`) so the
     * GitHubUserResolver canonicalization can't catch it either.
     * Add new entries as new non-human commit-author strings appear
     * in the wild.
     *
     * @var list<string>
     */
    private const NON_HUMAN_NAMES = ['Copilot', 'openemr-release-bot'];

    /**
     * Pattern matching a GitHub noreply commit email. Two shapes:
     *   - `<username>@users.noreply.github.com`           (older accounts)
     *   - `<user-id>+<username>@users.noreply.github.com` (post-2017,
     *      when GitHub started prefixing the user ID so username changes
     *      don't break history)
     * Named `username` group extracts the login for API lookup.
     */
    private const GITHUB_NOREPLY_PATTERN = '/^(?:\d+\+)?(?<username>[^@]+)@users\.noreply\.github\.com$/';

    public function __construct(
        private readonly ?GitHubUserResolver $resolver = null,
    ) {
    }

    public function generate(string $repoPath, string $fromRev, string $toRev, string $version): string
    {
        $commits = $this->logAuthors($repoPath, $fromRev, $toRev);
        $canonicalized = $this->canonicalizeGithubNoreplyNames($commits);
        $grouped = $this->groupByEmail($canonicalized);
        $merged = $this->mergeSameNameEntries($grouped);
        return $this->render($this->filterAutomatedAuthors($merged), $version);
    }

    /**
     * Rewrite the `name` on any per-commit record whose email is a
     * GitHub noreply address to the account's real display name (from
     * GitHub's public profile). Records with non-noreply emails pass
     * through unchanged; there's no username to look up.
     *
     * Why: Co-authored-by trailers typically ship as `<github-username>
     * <id+username@users.noreply.github.com>` (peter-evans + GitHub's
     * PR-merge-with-suggestions flow both use this shape). That row's
     * name (`bradymiller`) doesn't match the primary-author name
     * (`Brady Miller`) so groupByEmail's later name-based tie-break
     * plus mergeSameNameEntries's case-insensitive name merge both
     * miss the connection. Canonicalizing to the profile display name
     * lets mergeSameNameEntries collapse the two rows naturally.
     *
     * One API call per UNIQUE username (deduplicated at resolver-boundary
     * via array_unique), then the returned map is applied as an
     * in-process cache to every record needing it -- no re-lookups even
     * when the same username co-authors dozens of commits in one range.
     *
     * When no resolver is injected (unit-test convenience default), this
     * is a no-op -- records pass through unchanged, which preserves
     * pre-canonicalization semantics for the test suite. Production
     * builds inject HttpGitHubUserResolver::fromEnvironment().
     *
     * @param list<array{email: string, name: string}> $commits
     * @return list<array{email: string, name: string}>
     */
    public function canonicalizeGithubNoreplyNames(array $commits): array
    {
        if ($this->resolver === null) {
            return $commits;
        }

        $usernames = [];
        foreach ($commits as $commit) {
            if (preg_match(self::GITHUB_NOREPLY_PATTERN, $commit['email'], $m) === 1) {
                $usernames[] = $m['username'];
            }
        }
        if ($usernames === []) {
            return $commits;
        }

        $nameMap = $this->resolver->resolveNames($usernames);

        return array_map(
            function (array $commit) use ($nameMap): array {
                if (preg_match(self::GITHUB_NOREPLY_PATTERN, $commit['email'], $m) !== 1) {
                    return $commit;
                }
                $username = $m['username'];
                if (!isset($nameMap[$username])) {
                    return $commit;
                }
                $commit['name'] = $nameMap[$username];
                return $commit;
            },
            $commits,
        );
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
     * One record per commit-contribution in the range: the primary
     * author (%aE / %aN) plus one record per `Co-authored-by:` trailer
     * line in the commit message. Co-authors get full credit -- one
     * commit with N co-authors emits N+1 records total, so each named
     * contributor's row in the rendered page counts every commit they
     * contributed to. Matches GitHub's contribution-count semantics.
     *
     * @return list<array{email: string, name: string}>
     */
    public function logAuthors(string $repoPath, string $fromRev, string $toRev): array
    {
        // %aE gives the author email; %aN respects .mailmap so upstream
        // canonicalization (if any) is honored before we group.
        // %(trailers:key=Co-authored-by,valueonly=true,unfold=true,
        // separator=%x1e) gives the raw trailer values ("Name <email>"
        // each), RS-separated within one line so a per-line split still
        // works. Empty third field when the commit has no co-authors.
        // Tab as field separator is safe because commit author name/
        // email + trailer values can't contain tabs.
        //
        // `unfold=true` collapses folded (multi-line-continuation)
        // trailer values into a single line before emission -- without
        // it, a trailer whose value happens to be folded in the commit
        // would inject newlines into our per-line parse and split one
        // co-author across multiple records. Git's trailer syntax
        // permits folding on continuation lines starting with
        // whitespace; rare in practice but the pre-fix format left it
        // as a latent parse-corruption risk.
        $process = new Process([
            'git',
            '-C', $repoPath,
            'log',
            '--no-merges',
            '--format=%aE%x09%aN%x09%(trailers:key=Co-authored-by,valueonly=true,unfold=true,separator=%x1e)',
            "$fromRev..$toRev",
        ]);
        $process->mustRun();

        return $this->parseLogOutput($process->getOutput());
    }

    /**
     * Parse the tab-delimited git log output produced by logAuthors().
     * Each line = one commit: `email\tname[\tcoauthor1\x1ecoauthor2...]`.
     * The third field is optional (absent on the pre-fix format; empty
     * when the commit has no co-authors).
     *
     * Emits one record per contributor per commit (primary author +
     * one per Co-authored-by trailer). Malformed trailer values that
     * don't match the "Name <email>" shape are silently skipped --
     * git normalizes trailer syntax on read, so a malformed value
     * would indicate an upstream commit crafted with a broken trailer
     * (rare; not worth failing the whole render).
     *
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

            $parts = explode("\t", $line, 3);
            if (count($parts) < 2) {
                continue;
            }
            $email = trim($parts[0]);
            $name = trim($parts[1]);
            if ($email === '' || $name === '') {
                continue;
            }

            $commits[] = ['email' => $email, 'name' => $name];

            // Third field (optional) carries RS-separated Co-authored-by
            // trailer values. Each looks like "Name <email>" -- parse
            // and emit one record per co-author, giving them full
            // per-commit credit alongside the primary author.
            $coauthorField = $parts[2] ?? '';
            if ($coauthorField === '') {
                continue;
            }
            foreach ($this->parseCoauthorTrailers($coauthorField) as $coauthor) {
                $commits[] = $coauthor;
            }
        }

        return $commits;
    }

    /**
     * Parse the raw `%(trailers:key=Co-authored-by,valueonly=true,
     * separator=%x1e)` field into per-co-author records.
     *
     * Each value is expected to be a `Name <email>` string (git's
     * standard trailer syntax; enforced by peter-evans/create-pull-
     * request + GitHub's own commit-signature UI). Values that don't
     * match are dropped rather than crashing the render -- a badly
     * crafted trailer somewhere in history shouldn't fail the whole
     * acknowledgements page.
     *
     * @return list<array{email: string, name: string}>
     */
    private function parseCoauthorTrailers(string $field): array
    {
        $records = [];
        foreach (explode("\x1e", $field) as $value) {
            $value = trim($value);
            if ($value === '') {
                continue;
            }
            if (preg_match('/^(?<name>.+?)\s*<(?<email>[^>]+)>\s*$/', $value, $m) !== 1) {
                continue;
            }
            $name = trim($m['name']);
            $email = trim($m['email']);
            if ($name === '' || $email === '') {
                continue;
            }
            $records[] = ['email' => $email, 'name' => $name];
        }
        return $records;
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
