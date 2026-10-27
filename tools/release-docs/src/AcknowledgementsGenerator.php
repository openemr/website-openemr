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

    /**
     * Automated/non-human authors identified by exact-match commit email.
     * Dropped before any grouping (both primary-author and Co-authored-by
     * trailer records are filtered). Compared case-insensitively; entries
     * must be lowercase.
     *
     * Email is the stable identity for these authors; the display name
     * changes often enough that the NON_HUMAN_NAMES list can't keep up
     * (e.g. new Claude model names ship every few months, all under the
     * same anthropic.com noreply address). Covers:
     *   - LLM assistants committing under a vendor-wide noreply address
     *     (Claude Opus/Sonnet/Fable variants, Cursor, Warp).
     *   - OpenEMR's security sandbox automation, which runs regression
     *     passes under its own commit identity.
     *
     * @var list<string>
     */
    private const NON_HUMAN_EMAILS = [
        'noreply@anthropic.com',
        'cursoragent@cursor.com',
        'agent@warp.dev',
        'security@open-emr.org',
    ];

    /**
     * Identity rewrites applied before groupByEmail(). Keyed by source
     * email (lowercase); value is the canonical (name, email) pair that
     * replaces the record. One entry can do both jobs:
     *   - Fold a secondary email into a canonical one (two keys mapping
     *     to the same value email) merges a person's rows across both
     *     identities.
     *   - Normalize the display name on records that would otherwise win
     *     the pickDisplayName tie-break with the "wrong" spelling (set
     *     the key equal to value's email, override the name).
     *
     * @var array<string, array{name: string, email: string}>
     */
    private const IDENTITY_ALIASES = [
        // Discover and Change's "AI worker" commits are credited to the
        // maintainer the worker operates on behalf of.
        'ai-worker@discoverandchange.com' => [
            'name' => 'Stephen Nielson',
            'email' => 'snielson@discoverandchange.com',
        ],
        // Craig Allen commits from a personal email with the full
        // "Craig Allen" spelling and from the GitHub-noreply with the
        // "craigrallen" login spelling. Fold both into the noreply
        // email (the primary-author identity GitHub emits) and force
        // the full-name display so the row reads "Craig Allen" even
        // when the login spelling has more commits in a given range.
        'craig@interconnected.au' => [
            'name' => 'Craig Allen',
            'email' => '56693437+craigrallen@users.noreply.github.com',
        ],
        '56693437+craigrallen@users.noreply.github.com' => [
            'name' => 'Craig Allen',
            'email' => '56693437+craigrallen@users.noreply.github.com',
        ],
        // Brady Miller commits as primary under his personal email but
        // GitHub's squash-merge path annotates his own PR commits with
        // a Co-authored-by trailer under his GitHub-noreply identity.
        // Fold the noreply into the personal email so deduplicatePerCommit
        // collapses the primary + self-coauthor pair into one contribution.
        '278968+bradymiller@users.noreply.github.com' => [
            'name' => 'Brady Miller',
            'email' => 'brady.g.miller@gmail.com',
        ],
    ];

    public function generate(string $repoPath, string $fromRev, string $toRev, string $version): string
    {
        $commits = $this->logAuthors($repoPath, $fromRev, $toRev);
        // Two-stage canonicalization. Order matters: the API-driven step
        // runs first so the hardcoded aliases below have the final say
        // for any email they match (an explicit maintainer decision
        // beats whatever a GitHub profile lookup happens to return).
        $noreplyResolved = $this->canonicalizeGithubNoreplyNames($commits);
        $canonical = $this->canonicalizeIdentities($noreplyResolved);
        // Dedup per-commit: after both canonicalization passes, a single
        // commit's primary-author record and one of its Co-authored-by
        // trailers can resolve to the same (email, name) -- most often
        // when an aliased identity lands on the same canonical email as
        // the primary (Stephen Nielson's AI-worker trailer; Craig Allen's
        // personal-email trailer on his own primary commit). Each real
        // commit should count once per contributor, not once per record.
        $deduped = $this->deduplicatePerCommit($canonical);
        $grouped = $this->groupByEmail($deduped);
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
     * @param list<array{sha: string, email: string, name: string}> $commits
     * @return list<array{sha: string, email: string, name: string}>
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
     * Drop NON_HUMAN_EMAILS and apply IDENTITY_ALIASES to the flat
     * per-contribution log. Runs after canonicalizeGithubNoreplyNames()
     * so hardcoded maintainer aliases override whatever the GitHub
     * profile lookup produced for the same email.
     *
     * Both constants key on the lowercased email; comparisons here
     * lowercase the input email before lookup. Records with no match
     * in either constant pass through unchanged, so downstream display-
     * name resolution still sees the author's natural spellings.
     *
     * @param list<array{sha: string, email: string, name: string}> $commits
     * @return list<array{sha: string, email: string, name: string}>
     */
    public function canonicalizeIdentities(array $commits): array
    {
        $out = [];
        foreach ($commits as $commit) {
            $emailKey = strtolower($commit['email']);
            if (in_array($emailKey, self::NON_HUMAN_EMAILS, true)) {
                continue;
            }
            if (isset(self::IDENTITY_ALIASES[$emailKey])) {
                $alias = self::IDENTITY_ALIASES[$emailKey];
                $out[] = ['sha' => $commit['sha'], 'email' => $alias['email'], 'name' => $alias['name']];
                continue;
            }
            $out[] = $commit;
        }
        return $out;
    }

    /**
     * Collapse per-commit records that share the same canonical identity
     * after upstream canonicalization. Key is (sha, strtolower(email)):
     * two records on the same commit with the same email are the same
     * contributor contributing once, not twice. The emitted record drops
     * the sha field (downstream steps work on the flat identity shape).
     *
     * Why: logAuthors emits one record per primary + one per Co-authored-
     * by trailer, giving co-authors full per-commit credit. When an alias
     * folds a trailer identity onto the same email as the primary author
     * (Stephen Nielson's AI-worker trailer; Craig Allen's personal-email
     * trailer on his own commit), the real "one commit, one contributor"
     * contribution ends up as two records and the ack-page count doubles.
     * Dedup after both canonicalization passes so the full canonical
     * email is in hand before matching.
     *
     * Name is not part of the dedup key: within one commit, two records
     * with the same email but different name spellings are still the
     * same person, so keep the first-seen spelling and discard the
     * second (primary-author record wins by iteration order).
     *
     * @param list<array{sha: string, email: string, name: string}> $commits
     * @return list<array{email: string, name: string}>
     */
    public function deduplicatePerCommit(array $commits): array
    {
        /** @var array<string, true> $seen keyed by "<sha>\x00<lower-email>" */
        $seen = [];
        $out = [];
        foreach ($commits as $commit) {
            $key = $commit['sha'] . "\x00" . strtolower($commit['email']);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $out[] = ['email' => $commit['email'], 'name' => $commit['name']];
        }
        return $out;
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
     * Each record carries the commit's SHA so a downstream dedup step
     * can collapse records that resolve to the same canonical identity
     * on the same commit (primary + aliased co-author trailer).
     *
     * @return list<array{sha: string, email: string, name: string}>
     */
    public function logAuthors(string $repoPath, string $fromRev, string $toRev): array
    {
        // %H gives the full commit SHA. %aE gives the author email;
        // %aN respects .mailmap so upstream canonicalization (if any)
        // is honored before we group.
        // %(trailers:key=Co-authored-by,valueonly=true,unfold=true,
        // separator=%x1e) gives the raw trailer values ("Name <email>"
        // each), RS-separated within one line so a per-line split still
        // works. Empty fourth field when the commit has no co-authors.
        // Tab as field separator is safe because commit SHA + author
        // name/email + trailer values can't contain tabs.
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
            '--format=%H%x09%aE%x09%aN%x09%(trailers:key=Co-authored-by,valueonly=true,unfold=true,separator=%x1e)',
            "$fromRev..$toRev",
        ]);
        $process->mustRun();

        return $this->parseLogOutput($process->getOutput());
    }

    /**
     * Parse the tab-delimited git log output produced by logAuthors().
     * Each line = one commit: `sha\temail\tname[\tcoauthor1\x1ecoauthor2...]`.
     * The fourth field is optional (empty when the commit has no co-authors).
     *
     * Emits one record per contributor per commit (primary author +
     * one per Co-authored-by trailer), each carrying the commit SHA so
     * a downstream dedup step can collapse same-identity contributions
     * on the same commit. Malformed trailer values that don't match the
     * "Name <email>" shape are silently skipped -- git normalizes
     * trailer syntax on read, so a malformed value would indicate an
     * upstream commit crafted with a broken trailer (rare; not worth
     * failing the whole render).
     *
     * @return list<array{sha: string, email: string, name: string}>
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

            $parts = explode("\t", $line, 4);
            if (count($parts) < 3) {
                continue;
            }
            $sha = trim($parts[0]);
            $email = trim($parts[1]);
            $name = trim($parts[2]);
            if ($sha === '' || $email === '' || $name === '') {
                continue;
            }

            $commits[] = ['sha' => $sha, 'email' => $email, 'name' => $name];

            // Fourth field (optional) carries RS-separated Co-authored-by
            // trailer values. Each looks like "Name <email>" -- parse
            // and emit one record per co-author tagged with this commit's
            // SHA, giving them full per-commit credit alongside the
            // primary author.
            $coauthorField = $parts[3] ?? '';
            if ($coauthorField === '') {
                continue;
            }
            foreach ($this->parseCoauthorTrailers($sha, $coauthorField) as $coauthor) {
                $commits[] = $coauthor;
            }
        }

        return $commits;
    }

    /**
     * Parse the raw `%(trailers:key=Co-authored-by,valueonly=true,
     * separator=%x1e)` field into per-co-author records tagged with
     * the enclosing commit's SHA.
     *
     * Each value is expected to be a `Name <email>` string (git's
     * standard trailer syntax; enforced by peter-evans/create-pull-
     * request + GitHub's own commit-signature UI). Values that don't
     * match are dropped rather than crashing the render -- a badly
     * crafted trailer somewhere in history shouldn't fail the whole
     * acknowledgements page.
     *
     * @return list<array{sha: string, email: string, name: string}>
     */
    private function parseCoauthorTrailers(string $sha, string $field): array
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
            $records[] = ['sha' => $sha, 'email' => $email, 'name' => $name];
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
