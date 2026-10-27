<?php

declare(strict_types=1);

namespace OpenEMR\ReleaseDocs\Tests;

use OpenEMR\ReleaseDocs\AcknowledgementsGenerator;
use OpenEMR\ReleaseDocs\GitHubUserResolver;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class AcknowledgementsGeneratorTest extends TestCase
{
    private const FIXTURE_DIR = __DIR__ . '/fixtures/acknowledgements';

    public function testParseLogOutputExtractsShaEmailAndName(): void
    {
        $input = "aaa1111\talice@example.com\tAlice Smith\nbbb2222\tbob@example.com\tBob Jones\n";

        $commits = (new AcknowledgementsGenerator())->parseLogOutput($input);

        self::assertSame(
            [
                ['sha' => 'aaa1111', 'email' => 'alice@example.com', 'name' => 'Alice Smith'],
                ['sha' => 'bbb2222', 'email' => 'bob@example.com', 'name' => 'Bob Jones'],
            ],
            $commits,
        );
    }

    public function testParseLogOutputIgnoresBlankAndMalformedLines(): void
    {
        // Malformed = fewer than three fields (sha / email / name), or
        // any of those three blank after trim.
        $input = "aaa1111\talice@example.com\tAlice\n\nnotab-line\naaa2222\t\t\nbbb3333\tbob@example.com\tBob\n";

        $commits = (new AcknowledgementsGenerator())->parseLogOutput($input);

        self::assertSame(
            [
                ['sha' => 'aaa1111', 'email' => 'alice@example.com', 'name' => 'Alice'],
                ['sha' => 'bbb3333', 'email' => 'bob@example.com', 'name' => 'Bob'],
            ],
            $commits,
        );
    }

    public function testParseLogOutputExtractsCoauthorsFromTrailerField(): void
    {
        // Fourth tab-separated field carries RS-separated Co-authored-by
        // trailer values ("Name <email>" each). Each co-author gets its
        // own record for full per-commit credit, tagged with the same
        // commit SHA as the primary.
        $input = "aaa1111\talice@example.com\tAlice Smith"
            . "\tBob Jones <bob@example.com>\x1eCarla Diaz <carla@example.com>\n";

        $commits = (new AcknowledgementsGenerator())->parseLogOutput($input);

        self::assertSame(
            [
                ['sha' => 'aaa1111', 'email' => 'alice@example.com', 'name' => 'Alice Smith'],
                ['sha' => 'aaa1111', 'email' => 'bob@example.com', 'name' => 'Bob Jones'],
                ['sha' => 'aaa1111', 'email' => 'carla@example.com', 'name' => 'Carla Diaz'],
            ],
            $commits,
        );
    }

    public function testParseLogOutputDropsLineMissingPrimaryFields(): void
    {
        // A line with fewer than three fields (sha, email, name) is a
        // malformed primary record and is skipped. Guards against upstream
        // format drift silently emitting under-counted records.
        $input = "aaa1111\talice@example.com\n";

        $commits = (new AcknowledgementsGenerator())->parseLogOutput($input);

        self::assertSame([], $commits);
    }

    public function testParseLogOutputEmptyTrailerFieldEmitsPrimaryAuthorOnly(): void
    {
        // git emits an empty fourth field when the commit has no
        // Co-authored-by trailers. Line looks like "sha\temail\tname\t\n".
        // Primary author still emitted; no co-author records added.
        $input = "aaa1111\talice@example.com\tAlice Smith\t\n";

        $commits = (new AcknowledgementsGenerator())->parseLogOutput($input);

        self::assertSame(
            [['sha' => 'aaa1111', 'email' => 'alice@example.com', 'name' => 'Alice Smith']],
            $commits,
        );
    }

    public function testParseLogOutputMalformedCoauthorTrailerSkippedNotFatal(): void
    {
        // Trailer syntax expects "Name <email>". A malformed value in
        // history shouldn't crash the page render -- skip that one,
        // keep the rest.
        $input = "aaa1111\talice@example.com\tAlice\tno-brackets-here\x1eBob Jones <bob@example.com>\n";

        $commits = (new AcknowledgementsGenerator())->parseLogOutput($input);

        self::assertSame(
            [
                ['sha' => 'aaa1111', 'email' => 'alice@example.com', 'name' => 'Alice'],
                ['sha' => 'aaa1111', 'email' => 'bob@example.com', 'name' => 'Bob Jones'],
            ],
            $commits,
        );
    }

    public function testParseLogOutputCoauthorWithWhitespaceIsTrimmed(): void
    {
        // Trailer values with leading/trailing whitespace still parse
        // cleanly. Also covers the "  <email>" spacing convention.
        $input = "aaa1111\talice@example.com\tAlice\t  Bob Jones   <bob@example.com>  \n";

        $commits = (new AcknowledgementsGenerator())->parseLogOutput($input);

        self::assertSame(
            [
                ['sha' => 'aaa1111', 'email' => 'alice@example.com', 'name' => 'Alice'],
                ['sha' => 'aaa1111', 'email' => 'bob@example.com', 'name' => 'Bob Jones'],
            ],
            $commits,
        );
    }

    public function testCoauthorContributionsFullyCredited(): void
    {
        // End-to-end: primary + co-author on the same commit both
        // count 1 in the acknowledgements. Simulates the 8.4.1
        // scenario that surfaced this gap (Brady primary + Stephen
        // co-author on a single commit should produce two 1-count
        // rows, not one 1-count Brady row).
        $input = "aaa1111\tbrady@example.com\tBrady Miller\tStephen Waite <stephen@example.com>\n";
        $generator = new AcknowledgementsGenerator();
        $commits = $generator->parseLogOutput($input);
        $deduped = $generator->deduplicatePerCommit($commits);
        $grouped = $generator->groupByEmail($deduped);

        self::assertSame(
            [
                ['name' => 'Brady Miller', 'commits' => 1],
                ['name' => 'Stephen Waite', 'commits' => 1],
            ],
            $grouped,
        );
    }

    public function testLogAuthorsUnfoldsFoldedCoauthorTrailerFromRealRepo(): void
    {
        // Rabbit-caught corner (2026-09-18): git trailers can be
        // folded (continuation line starts with whitespace).
        // Without `unfold=true` on the format string, the newline
        // inside a folded value would inject into our per-line parse
        // and split one co-author across two records. Integration
        // test creates a real repo with a folded-trailer commit and
        // asserts logAuthors() returns the co-author whole.
        $repoPath = sys_get_temp_dir() . '/openemr-ack-int-' . bin2hex(random_bytes(6));
        mkdir($repoPath);
        try {
            $this->runGit($repoPath, ['init', '--quiet', '--initial-branch=main']);
            $this->runGit($repoPath, ['config', 'commit.gpgsign', 'false']);
            $this->runGit($repoPath, ['config', 'tag.gpgsign', 'false']);
            $this->runGit($repoPath, ['config', 'user.name', 'Primary Author']);
            $this->runGit($repoPath, ['config', 'user.email', 'primary@example.com']);

            // Baseline commit -- provides the fromRev anchor.
            $this->runGit($repoPath, ['commit', '--allow-empty', '-m', 'baseline']);
            $fromRev = trim($this->runGit($repoPath, ['rev-parse', 'HEAD']));

            // Commit with a FOLDED Co-authored-by trailer. Continuation
            // line (starting with a single space) is git's fold syntax.
            $foldedMessage = <<<'MSG'
            feat: something

            Body paragraph explaining the change.

            Co-authored-by: Really Long Continuation Name
             <folded@example.com>
            MSG;
            $this->runGit($repoPath, ['commit', '--allow-empty', '-m', $foldedMessage]);
            $toRev = trim($this->runGit($repoPath, ['rev-parse', 'HEAD']));

            $records = (new AcknowledgementsGenerator())->logAuthors($repoPath, $fromRev, $toRev);

            // Expect 2 records: primary author + one unfolded co-author.
            // If unfold=true weren't set, the co-author's name would
            // parse as "Really Long Continuation Name" with no email
            // (regex would fail on the malformed value) OR would emit
            // one record with a truncated/broken name -- either way
            // the assertion below would fail. SHA is produced by git
            // so we strip it here before comparing to a known shape.
            $strippedShas = array_map(
                static fn(array $r): array => ['email' => $r['email'], 'name' => $r['name']],
                $records,
            );
            self::assertSame(
                [
                    ['email' => 'primary@example.com', 'name' => 'Primary Author'],
                    ['email' => 'folded@example.com', 'name' => 'Really Long Continuation Name'],
                ],
                $strippedShas,
            );
            // Each record carries its commit's full SHA (40 hex chars).
            self::assertSame($toRev, $records[0]['sha']);
            self::assertSame($toRev, $records[1]['sha']);
        } finally {
            $this->removeRecursive($repoPath);
        }
    }

    /**
     * @param list<string> $args
     */
    private function runGit(string $repoPath, array $args): string
    {
        $process = new \Symfony\Component\Process\Process(
            array_merge(['git', '-C', $repoPath], $args),
        );
        $process->mustRun();
        return $process->getOutput();
    }

    private function removeRecursive(string $path): void
    {
        if (!is_dir($path)) {
            if (is_file($path) || is_link($path)) {
                unlink($path);
            }
            return;
        }
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );
        /** @var \SplFileInfo $entry */
        foreach ($iterator as $entry) {
            $p = $entry->getPathname();
            if ($entry->isDir() && !$entry->isLink()) {
                rmdir($p);
            } else {
                unlink($p);
            }
        }
        rmdir($path);
    }

    public function testGroupByEmailCollapsesMultipleNameSpellings(): void
    {
        // Reproduces the #135 failure: Stephen Waite commits under
        // three spellings tied to the same email.
        $commits = array_merge(
            array_fill(0, 16, ['email' => 'stephen@example.com', 'name' => 'steve waite']),
            array_fill(0, 12, ['email' => 'stephen@example.com', 'name' => 'Stephen Waite']),
            array_fill(0, 1, ['email' => 'stephen@example.com', 'name' => 'stephen waite']),
        );

        $grouped = (new AcknowledgementsGenerator())->groupByEmail($commits);

        self::assertSame(
            // Total 29 commits, display name = most-used spelling (16-count).
            [['name' => 'steve waite', 'commits' => 29]],
            $grouped,
        );
    }

    public function testGroupByEmailIsCaseInsensitiveOnTheEmailKey(): void
    {
        // Author emails are canonically lowercase; treat mixed-case
        // variants as the same identity.
        $commits = [
            ['email' => 'Alice@Example.com', 'name' => 'Alice'],
            ['email' => 'alice@example.com', 'name' => 'Alice'],
            ['email' => 'ALICE@example.com', 'name' => 'Alice'],
        ];

        $grouped = (new AcknowledgementsGenerator())->groupByEmail($commits);

        self::assertSame([['name' => 'Alice', 'commits' => 3]], $grouped);
    }

    public function testGroupByEmailTieBreaksOnLongestNameThenAlphabetical(): void
    {
        // Two spellings for the same person tied on commit count. The
        // longer name wins (a fully-qualified "Firstname Lastname" beats
        // a shorter nickname or bare-email name).
        $commits = array_merge(
            array_fill(0, 5, ['email' => 'chris@example.com', 'name' => 'cdx@rolling.ventures']),
            array_fill(0, 5, ['email' => 'chris@example.com', 'name' => 'Chris Dickman']),
        );

        $grouped = (new AcknowledgementsGenerator())->groupByEmail($commits);

        self::assertSame(
            // Tied at 5 commits; "cdx@rolling.ventures" (20 chars) is
            // longer than "Chris Dickman" (13 chars), so longest wins.
            [['name' => 'cdx@rolling.ventures', 'commits' => 10]],
            $grouped,
        );
    }

    public function testGroupByEmailTieBreaksAlphabeticallyWhenNamesEqualLength(): void
    {
        // Same length + same count -> alphabetical (uppercase C < lowercase c).
        $commits = array_merge(
            array_fill(0, 3, ['email' => 'carol@example.com', 'name' => 'Carol Lee']),
            array_fill(0, 3, ['email' => 'carol@example.com', 'name' => 'carol lee']),
        );

        $grouped = (new AcknowledgementsGenerator())->groupByEmail($commits);

        self::assertSame(
            [['name' => 'Carol Lee', 'commits' => 6]],
            $grouped,
        );
    }

    public function testGroupByEmailSortsByCommitsDescThenNameAsc(): void
    {
        $commits = array_merge(
            array_fill(0, 3, ['email' => 'alice@example.com', 'name' => 'Alice']),
            array_fill(0, 3, ['email' => 'bob@example.com', 'name' => 'Bob']),
            array_fill(0, 5, ['email' => 'zack@example.com', 'name' => 'Zack']),
        );

        $grouped = (new AcknowledgementsGenerator())->groupByEmail($commits);

        self::assertSame(
            [
                ['name' => 'Zack', 'commits' => 5],
                // Ties broken by name asc: Alice before Bob.
                ['name' => 'Alice', 'commits' => 3],
                ['name' => 'Bob', 'commits' => 3],
            ],
            $grouped,
        );
    }

    public function testMergeSameNameEntriesCollapsesSameNameAcrossDifferentEmails(): void
    {
        // Reproduces the Eric Stern / Fabricio Orrala case: same
        // person commits from two email addresses under the same
        // display name. Email-based grouping alone leaves them as
        // two rows; this second pass merges them.
        $entries = [
            ['name' => 'Eric Stern', 'commits' => 213],
            ['name' => 'Eric Stern', 'commits' => 3],
            ['name' => 'Alice Solo', 'commits' => 42],
        ];

        $merged = (new AcknowledgementsGenerator())->mergeSameNameEntries($entries);

        self::assertSame(
            [
                ['name' => 'Eric Stern', 'commits' => 216],
                ['name' => 'Alice Solo', 'commits' => 42],
            ],
            $merged,
        );
    }

    public function testMergeSameNameEntriesIsCaseInsensitive(): void
    {
        // Someone commits under "Alice Smith" from one email and
        // "alice smith" from another. Case-insensitive name match
        // should merge them.
        $entries = [
            ['name' => 'Alice Smith', 'commits' => 10],
            ['name' => 'alice smith', 'commits' => 3],
        ];

        $merged = (new AcknowledgementsGenerator())->mergeSameNameEntries($entries);

        self::assertSame(
            // "Alice Smith" wins the display-name tie-break (higher
            // count contributes more weight).
            [['name' => 'Alice Smith', 'commits' => 13]],
            $merged,
        );
    }

    public function testMergeSameNameEntriesHandlesAccentedCharactersViaMbStrtolower(): void
    {
        // strtolower is byte-level and doesn't fold accented characters
        // (é/É produce different bytes), so a plain strtolower key would
        // fail to merge "François" / "françois". mb_strtolower with
        // UTF-8 folds correctly.
        $entries = [
            ['name' => 'François Dupont', 'commits' => 5],
            ['name' => 'françois dupont', 'commits' => 3],
        ];

        $merged = (new AcknowledgementsGenerator())->mergeSameNameEntries($entries);

        self::assertSame(
            [['name' => 'François Dupont', 'commits' => 8]],
            $merged,
        );
    }

    public function testMergeSameNameEntriesPreservesCjkNamesUnchanged(): void
    {
        // CJK characters have no case; mb_strtolower is a no-op on them.
        // Two entries that share a CJK name should merge exactly; two
        // that differ should stay separate.
        $entries = [
            ['name' => '李明', 'commits' => 5],
            ['name' => '李明', 'commits' => 3],
            ['name' => '张伟', 'commits' => 2],
        ];

        $merged = (new AcknowledgementsGenerator())->mergeSameNameEntries($entries);

        self::assertCount(2, $merged);
        self::assertSame(['name' => '李明', 'commits' => 8], $merged[0]);
        self::assertSame(['name' => '张伟', 'commits' => 2], $merged[1]);
    }

    public function testMergeSameNameEntriesLeavesUniqueEntriesAlone(): void
    {
        $entries = [
            ['name' => 'Alice', 'commits' => 10],
            ['name' => 'Bob', 'commits' => 5],
            ['name' => 'Carol', 'commits' => 2],
        ];

        $merged = (new AcknowledgementsGenerator())->mergeSameNameEntries($entries);

        self::assertSame($entries, $merged);
    }

    public function testFilterAutomatedAuthorsDropsBotAuthorsAndReindexes(): void
    {
        $authors = (new AcknowledgementsGenerator())->filterAutomatedAuthors([
            ['name' => 'Test Author One', 'commits' => 142],
            ['name' => 'dependabot[bot]', 'commits' => 87],
            ['name' => 'Test Author Two', 'commits' => 54],
            ['name' => 'openemr-reserved-word-bot[bot]', 'commits' => 8],
        ]);

        self::assertSame(
            [
                ['name' => 'Test Author One', 'commits' => 142],
                ['name' => 'Test Author Two', 'commits' => 54],
            ],
            $authors,
        );
    }

    public function testFilterAutomatedAuthorsOnlyMatchesTrailingBotSuffix(): void
    {
        // A hypothetical human contributor whose display name happens to
        // contain "[bot]" in the middle isn't dropped; only the trailing-
        // suffix pattern (used by GitHub App identities) is filtered.
        $authors = (new AcknowledgementsGenerator())->filterAutomatedAuthors([
            ['name' => 'Alice [bot maintainer] Smith', 'commits' => 5],
            ['name' => 'noisy[bot]', 'commits' => 100],
        ]);

        self::assertSame(
            [['name' => 'Alice [bot maintainer] Smith', 'commits' => 5]],
            $authors,
        );
    }

    public function testFilterAutomatedAuthorsDropsNonBotNonHumans(): void
    {
        // Copilot (and other future LLM/IDE assistants) commit under a
        // bare name with no `[bot]` suffix, so the bot-suffix rule alone
        // wouldn't catch them. The NON_HUMAN_NAMES blocklist handles
        // that case -- Copilot's ~16 commits on the 8.2.0 release cycle
        // were the concrete driver for adding it (see G25 in the
        // openemr/openemr release-mechanism-gaps doc).
        $authors = (new AcknowledgementsGenerator())->filterAutomatedAuthors([
            ['name' => 'Test Author One', 'commits' => 142],
            ['name' => 'Copilot', 'commits' => 16],
            ['name' => 'Test Author Two', 'commits' => 54],
        ]);

        self::assertSame(
            [
                ['name' => 'Test Author One', 'commits' => 142],
                ['name' => 'Test Author Two', 'commits' => 54],
            ],
            $authors,
        );
    }

    public function testFilterAutomatedAuthorsDropsOpenemrReleaseBotCoauthorVariant(): void
    {
        // The openemr-release-bot GitHub App commits under its
        // primary-author name `openemr-release-bot[bot]` which is
        // caught by the `[bot]`-suffix rule. But some conductor-
        // generated commits carry a Co-authored-by trailer of
        // `openemr-release-bot <release-bot@openemr.invalid>` -- no
        // `[bot]` suffix, and a non-GitHub-noreply email so the
        // GitHubUserResolver canonicalization pass can't catch it
        // either. NON_HUMAN_NAMES gets the bare-name variant added
        // to catch this case explicitly (surfaced on release-docs
        // 8.4.1 acknowledgements page, 2026-09-19).
        $authors = (new AcknowledgementsGenerator())->filterAutomatedAuthors([
            ['name' => 'Brady Miller', 'commits' => 12],
            ['name' => 'openemr-release-bot', 'commits' => 2],
            ['name' => 'openemr-release-bot[bot]', 'commits' => 9],
        ]);

        self::assertSame(
            [['name' => 'Brady Miller', 'commits' => 12]],
            $authors,
        );
    }

    public function testFilterAutomatedAuthorsPreservesNamesThatMerelyContainNonHumanSubstring(): void
    {
        // A hypothetical human contributor whose display name is a
        // superset of the blocklist entry (case matters, and only exact
        // full-name matches are dropped) is preserved. Only exact-match
        // membership in NON_HUMAN_NAMES triggers the drop.
        $authors = (new AcknowledgementsGenerator())->filterAutomatedAuthors([
            ['name' => 'Copilot Enthusiast', 'commits' => 5],
            ['name' => 'copilot', 'commits' => 3],
            ['name' => 'Copilot', 'commits' => 100],
        ]);

        self::assertSame(
            [
                ['name' => 'Copilot Enthusiast', 'commits' => 5],
                ['name' => 'copilot', 'commits' => 3],
            ],
            $authors,
        );
    }

    public function testCanonicalizeGithubNoreplyNamesNoopWhenNoResolverInjected(): void
    {
        // Backward-compat guarantee: constructor with no resolver leaves
        // records untouched. Preserves pre-canonicalization semantics
        // for callers that don't wire an HTTP resolver (test suite +
        // any lightweight consumer that doesn't need canonicalization).
        $commits = [
            ['sha' => 'aaa1', 'email' => '278968+bradymiller@users.noreply.github.com', 'name' => 'bradymiller'],
            ['sha' => 'aaa2', 'email' => 'brady.g.miller@gmail.com', 'name' => 'Brady Miller'],
        ];
        $result = (new AcknowledgementsGenerator())->canonicalizeGithubNoreplyNames($commits);
        self::assertSame($commits, $result);
    }

    public function testCanonicalizeGithubNoreplyNamesRewritesNamesForNoreplyEmails(): void
    {
        // Real 8.4.1 shape (see release-docs 8.4.1 acknowledgements
        // 2026-09-19): `bradymiller <278968+bradymiller@users.noreply
        // .github.com>` co-author trailer + `Brady Miller <brady.g.
        // miller@gmail.com>` primary. Post-canonicalization, both
        // rows use the same name string so mergeSameNameEntries later
        // collapses them.
        $resolver = new class implements GitHubUserResolver {
            /** @param list<string> $usernames  @return array<string, string> */
            public function resolveNames(array $usernames): array
            {
                $map = ['bradymiller' => 'Brady Miller', 'kojiromike' => 'Michael Smith'];
                $out = [];
                foreach (array_unique($usernames) as $u) {
                    $out[$u] = $map[$u] ?? $u;
                }
                return $out;
            }
        };

        $commits = [
            ['sha' => 'c1', 'email' => 'brady.g.miller@gmail.com', 'name' => 'Brady Miller'],
            ['sha' => 'c2', 'email' => '278968+bradymiller@users.noreply.github.com', 'name' => 'bradymiller'],
            ['sha' => 'c3', 'email' => '1566303+kojiromike@users.noreply.github.com', 'name' => 'kojiromike'],
            ['sha' => 'c4', 'email' => 'jerry@example.com', 'name' => 'Jerry Padgett'],
        ];

        $result = (new AcknowledgementsGenerator($resolver))->canonicalizeGithubNoreplyNames($commits);

        self::assertSame(
            [
                // Primary-author record (non-noreply email) untouched.
                ['sha' => 'c1', 'email' => 'brady.g.miller@gmail.com', 'name' => 'Brady Miller'],
                // Co-author records (noreply emails) get canonicalized names.
                ['sha' => 'c2', 'email' => '278968+bradymiller@users.noreply.github.com', 'name' => 'Brady Miller'],
                ['sha' => 'c3', 'email' => '1566303+kojiromike@users.noreply.github.com', 'name' => 'Michael Smith'],
                // Non-noreply co-author (personal email) untouched.
                ['sha' => 'c4', 'email' => 'jerry@example.com', 'name' => 'Jerry Padgett'],
            ],
            $result,
        );
    }

    public function testCanonicalizeGithubNoreplyNamesHandlesLegacyNoreplyFormat(): void
    {
        // Older GitHub accounts use `<username>@users.noreply.github.com`
        // without the `<id>+` prefix. Both formats must be recognized.
        $resolver = new class implements GitHubUserResolver {
            /** @param list<string> $usernames  @return array<string, string> */
            public function resolveNames(array $usernames): array
            {
                $out = [];
                foreach (array_unique($usernames) as $u) {
                    $out[$u] = $u === 'olduser' ? 'Old User Real Name' : $u;
                }
                return $out;
            }
        };

        $commits = [
            ['sha' => 'c1', 'email' => 'olduser@users.noreply.github.com', 'name' => 'olduser'],
        ];
        $result = (new AcknowledgementsGenerator($resolver))->canonicalizeGithubNoreplyNames($commits);
        self::assertSame(
            [['sha' => 'c1', 'email' => 'olduser@users.noreply.github.com', 'name' => 'Old User Real Name']],
            $result,
        );
    }

    public function testCanonicalizeGithubNoreplyNamesDedupsResolverCallsPerUniqueUsername(): void
    {
        // Hot-cache semantics: even if the same username co-authors N
        // commits, the resolver is asked exactly once per unique
        // username. Captures the resolver's input to assert the dedup.
        $resolver = new class implements GitHubUserResolver {
            /** @var list<list<string>> */
            public array $capturedInputs = [];

            /** @param list<string> $usernames  @return array<string, string> */
            public function resolveNames(array $usernames): array
            {
                $this->capturedInputs[] = $usernames;
                $out = [];
                foreach (array_unique($usernames) as $u) {
                    $out[$u] = 'Resolved ' . $u;
                }
                return $out;
            }
        };

        // Same username appears 5x on 5 different commits.
        $commits = [];
        for ($i = 1; $i <= 5; $i++) {
            $commits[] = [
                'sha' => "commit$i",
                'email' => '278968+bradymiller@users.noreply.github.com',
                'name' => 'bradymiller',
            ];
        }
        (new AcknowledgementsGenerator($resolver))->canonicalizeGithubNoreplyNames($commits);

        self::assertCount(1, $resolver->capturedInputs, 'resolveNames called exactly once');
        // The input list passed in HAS duplicates (dedup is the
        // resolver's responsibility per its interface contract); the
        // resolver's `array_unique` collapses them to one API call.
        self::assertCount(5, $resolver->capturedInputs[0], 'generator hands full non-deduped list to resolver');
        self::assertSame(['bradymiller'], array_values(array_unique($resolver->capturedInputs[0])));
    }

    public function testCanonicalizeGithubNoreplyNamesPreservesRecordsWhenResolverOmitsAUsername(): void
    {
        // Resolver contract allows returning missing keys (e.g. lookup
        // failed for that username). Generator falls back to the
        // original name string when the map has no entry.
        $resolver = new class implements GitHubUserResolver {
            /** @param list<string> $usernames  @return array<string, string> */
            public function resolveNames(array $usernames): array
            {
                return []; // simulate: every lookup failed
            }
        };
        $commits = [
            ['sha' => 'c1', 'email' => '278968+bradymiller@users.noreply.github.com', 'name' => 'bradymiller'],
        ];
        $result = (new AcknowledgementsGenerator($resolver))->canonicalizeGithubNoreplyNames($commits);
        self::assertSame($commits, $result, 'missing map entry falls back to original record');
    }

    public function testCanonicalizedNamesEnableMergeSameNameEntriesToCollapseDuplicateRows(): void
    {
        // End-to-end assertion of the real 8.4.1 acknowledgements bug:
        // Brady Miller primary + bradymiller co-author (via GitHub
        // noreply email) should NOT produce two rows for the same
        // person. Post-canonicalization -> mergeSameNameEntries
        // collapses them into one row with the combined count. The
        // 6 primary records and 6 co-author records are on 12
        // distinct commits (co-author trailers crediting Brady on
        // commits where he's not the primary author), so dedup
        // doesn't fire and the final count is 12.
        $resolver = new class implements GitHubUserResolver {
            /** @param list<string> $usernames  @return array<string, string> */
            public function resolveNames(array $usernames): array
            {
                $out = [];
                foreach (array_unique($usernames) as $u) {
                    $out[$u] = $u === 'bradymiller' ? 'Brady Miller' : $u;
                }
                return $out;
            }
        };
        $generator = new AcknowledgementsGenerator($resolver);

        // 12 distinct commits: 6 with Brady as primary, 6 with Brady
        // as co-author (crediting Brady on someone else's commit).
        $commits = [];
        for ($i = 1; $i <= 6; $i++) {
            $commits[] = ['sha' => "p$i", 'email' => 'brady.g.miller@gmail.com', 'name' => 'Brady Miller'];
        }
        for ($i = 1; $i <= 6; $i++) {
            $commits[] = [
                'sha' => "co$i",
                'email' => '278968+bradymiller@users.noreply.github.com',
                'name' => 'bradymiller',
            ];
        }

        $canonicalized = $generator->canonicalizeGithubNoreplyNames($commits);
        $deduped = $generator->deduplicatePerCommit($canonicalized);
        $grouped = $generator->groupByEmail($deduped);
        $merged = $generator->mergeSameNameEntries($grouped);

        self::assertSame(
            [['name' => 'Brady Miller', 'commits' => 12]],
            $merged,
        );
    }

    public function testCanonicalizeDropsNonHumanEmails(): void
    {
        // Email-keyed drop runs before any grouping, so both primary-
        // author records and Co-authored-by trailers tied to a known
        // bot email are removed. Human records with other emails pass
        // through unchanged.
        $commits = [
            ['sha' => 'c1', 'email' => 'noreply@anthropic.com', 'name' => 'Claude Opus 5.5'],
            ['sha' => 'c2', 'email' => 'noreply@anthropic.com', 'name' => 'Claude Sonnet 5'],
            ['sha' => 'c3', 'email' => 'cursoragent@cursor.com', 'name' => 'Cursor'],
            ['sha' => 'c4', 'email' => 'agent@warp.dev', 'name' => 'Warp'],
            ['sha' => 'c5', 'email' => 'security@open-emr.org', 'name' => 'OpenEMR Security Sandbox'],
            ['sha' => 'c6', 'email' => 'alice@example.com', 'name' => 'Alice'],
        ];

        $canonical = (new AcknowledgementsGenerator())->canonicalizeIdentities($commits);

        self::assertSame(
            [['sha' => 'c6', 'email' => 'alice@example.com', 'name' => 'Alice']],
            $canonical,
        );
    }

    public function testCanonicalizeNonHumanEmailMatchIsCaseInsensitive(): void
    {
        // Author emails are canonically lowercase, but a mixed-case
        // variant in commit history (unusual but legal per RFC 5321)
        // shouldn't slip past the filter.
        $commits = [
            ['sha' => 'c1', 'email' => 'NoReply@Anthropic.COM', 'name' => 'Claude Opus 5.5'],
        ];

        $canonical = (new AcknowledgementsGenerator())->canonicalizeIdentities($commits);

        self::assertSame([], $canonical);
    }

    public function testCanonicalizeAliasesAiWorkerToStephenNielson(): void
    {
        // The AI worker's commits are credited to Stephen Nielson's
        // canonical identity -- same (name, email) that Stephen's
        // own commits use. Two distinct commits here (different SHAs)
        // so dedup doesn't fire and groupByEmail reports 2.
        $commits = [
            ['sha' => 'c1', 'email' => 'ai-worker@discoverandchange.com', 'name' => 'Discover and Change AI Worker'],
            ['sha' => 'c2', 'email' => 'snielson@discoverandchange.com', 'name' => 'Stephen Nielson'],
        ];
        $generator = new AcknowledgementsGenerator();

        $canonical = $generator->canonicalizeIdentities($commits);

        self::assertSame(
            [
                ['sha' => 'c1', 'email' => 'snielson@discoverandchange.com', 'name' => 'Stephen Nielson'],
                ['sha' => 'c2', 'email' => 'snielson@discoverandchange.com', 'name' => 'Stephen Nielson'],
            ],
            $canonical,
        );
        self::assertSame(
            [['name' => 'Stephen Nielson', 'commits' => 2]],
            $generator->groupByEmail($generator->deduplicatePerCommit($canonical)),
        );
    }

    public function testCanonicalizeMergesCraigAllenAcrossTwoEmails(): void
    {
        // Both of Craig's committer identities map to the same canonical
        // (name, email), so groupByEmail folds them into one row and the
        // display name is the full-name spelling regardless of which
        // identity contributed more commits. All records are on
        // distinct commits so dedup doesn't fire.
        $commits = [];
        for ($i = 1; $i <= 3; $i++) {
            $commits[] = [
                'sha' => "p$i",
                'email' => '56693437+craigrallen@users.noreply.github.com',
                'name' => 'craigrallen',
            ];
        }
        $commits[] = [
            'sha' => 'co1',
            'email' => 'craig@interconnected.au',
            'name' => 'Craig Allen',
        ];
        $generator = new AcknowledgementsGenerator();

        $grouped = $generator->groupByEmail(
            $generator->deduplicatePerCommit($generator->canonicalizeIdentities($commits)),
        );

        self::assertSame(
            [['name' => 'Craig Allen', 'commits' => 4]],
            $grouped,
        );
    }

    public function testCanonicalizeLeavesUnknownIdentitiesUntouched(): void
    {
        // No match in either constant -> record passes through byte-exact.
        $commits = [
            ['sha' => 'c1', 'email' => 'alice@example.com', 'name' => 'Alice Smith'],
            ['sha' => 'c2', 'email' => 'bob@example.com', 'name' => 'Bob Jones'],
        ];

        $canonical = (new AcknowledgementsGenerator())->canonicalizeIdentities($commits);

        self::assertSame($commits, $canonical);
    }

    public function testDeduplicatePerCommitCollapsesSameCommitSameEmail(): void
    {
        // Real-world case: Stephen Nielson is primary + AI-worker
        // trailer on the same commit, both resolve to Stephen's
        // canonical email post-alias. Two records on one commit =
        // one contribution, not two.
        $commits = [
            ['sha' => 'commit-xyz', 'email' => 'snielson@discoverandchange.com', 'name' => 'Stephen Nielson'],
            ['sha' => 'commit-xyz', 'email' => 'snielson@discoverandchange.com', 'name' => 'Stephen Nielson'],
        ];

        $deduped = (new AcknowledgementsGenerator())->deduplicatePerCommit($commits);

        self::assertSame(
            [['email' => 'snielson@discoverandchange.com', 'name' => 'Stephen Nielson']],
            $deduped,
        );
    }

    public function testDeduplicatePerCommitKeepsDistinctEmailsOnSameCommit(): void
    {
        // Two different people on the same commit (primary + genuine
        // co-author trailer for a different contributor) must stay
        // as two records -- that's two contributors sharing credit.
        $commits = [
            ['sha' => 'commit-xyz', 'email' => 'brady@example.com', 'name' => 'Brady Miller'],
            ['sha' => 'commit-xyz', 'email' => 'stephen@example.com', 'name' => 'Stephen Nielson'],
        ];

        $deduped = (new AcknowledgementsGenerator())->deduplicatePerCommit($commits);

        self::assertSame(
            [
                ['email' => 'brady@example.com', 'name' => 'Brady Miller'],
                ['email' => 'stephen@example.com', 'name' => 'Stephen Nielson'],
            ],
            $deduped,
        );
    }

    public function testDeduplicatePerCommitKeepsSameEmailOnDistinctCommits(): void
    {
        // The same contributor on two distinct commits is two
        // contributions, not one -- dedup is strictly within-commit.
        $commits = [
            ['sha' => 'commit-A', 'email' => 'brady@example.com', 'name' => 'Brady Miller'],
            ['sha' => 'commit-B', 'email' => 'brady@example.com', 'name' => 'Brady Miller'],
        ];

        $deduped = (new AcknowledgementsGenerator())->deduplicatePerCommit($commits);

        self::assertSame(
            [
                ['email' => 'brady@example.com', 'name' => 'Brady Miller'],
                ['email' => 'brady@example.com', 'name' => 'Brady Miller'],
            ],
            $deduped,
        );
    }

    public function testDeduplicatePerCommitDedupIsCaseInsensitiveOnEmail(): void
    {
        // Email keyed case-insensitively, matching groupByEmail's convention.
        $commits = [
            ['sha' => 'commit-xyz', 'email' => 'Brady@Example.COM', 'name' => 'Brady Miller'],
            ['sha' => 'commit-xyz', 'email' => 'brady@example.com', 'name' => 'Brady Miller'],
        ];

        $deduped = (new AcknowledgementsGenerator())->deduplicatePerCommit($commits);

        self::assertSame(
            [['email' => 'Brady@Example.COM', 'name' => 'Brady Miller']],
            $deduped,
        );
    }

    public function testEndToEndStephenNielsonAiWorkerSameCommitCountedOnce(): void
    {
        // The real-world shape from commit 540334297 (v8_4_0..HEAD):
        // Stephen Nielson is the primary author AND the Co-authored-by
        // trailer "Discover and Change AI Worker" aliases to Stephen.
        // Pre-fix this counted 2 for that commit; post-fix counts 1.
        $input = "540334297\tsnielson@discoverandchange.com\tStephen Nielson"
            . "\tDiscover and Change AI Worker <ai-worker@discoverandchange.com>\n";
        $generator = new AcknowledgementsGenerator();

        $commits = $generator->parseLogOutput($input);
        $canonical = $generator->canonicalizeIdentities($commits);
        $deduped = $generator->deduplicatePerCommit($canonical);
        $grouped = $generator->groupByEmail($deduped);

        self::assertSame(
            [['name' => 'Stephen Nielson', 'commits' => 1]],
            $grouped,
        );
    }

    public function testGenerateRendersStephenNielsonSelfCoauthorAsOneCommit(): void
    {
        // Pipeline-level regression gate: exercise the real generate()
        // wiring (not individual steps) so a reorder or removal of
        // canonicalizeIdentities / deduplicatePerCommit in generate()
        // is caught here. Mirrors the shape of commit 540334297 from
        // openemr/openemr: Stephen Nielson is primary, Discover and
        // Change AI Worker is the Co-authored-by trailer on the same
        // commit; both must fold into one "Stephen Nielson (1 commit)"
        // row in the rendered output.
        $repoPath = sys_get_temp_dir() . '/openemr-ack-gen-' . bin2hex(random_bytes(6));
        mkdir($repoPath);
        try {
            $this->runGit($repoPath, ['init', '--quiet', '--initial-branch=main']);
            $this->runGit($repoPath, ['config', 'commit.gpgsign', 'false']);
            $this->runGit($repoPath, ['config', 'tag.gpgsign', 'false']);
            $this->runGit($repoPath, ['config', 'user.name', 'Stephen Nielson']);
            $this->runGit($repoPath, ['config', 'user.email', 'snielson@discoverandchange.com']);

            // Baseline commit = fromRev anchor; excluded from the range.
            $this->runGit($repoPath, ['commit', '--allow-empty', '-m', 'baseline']);
            $fromRev = trim($this->runGit($repoPath, ['rev-parse', 'HEAD']));

            // Stephen primary + AI-worker self-coauthor trailer on
            // the same commit. The AI-worker email is in IDENTITY_
            // ALIASES mapping to Stephen's canonical identity.
            $message = <<<'MSG'
            feat: something

            Co-authored-by: Discover and Change AI Worker <ai-worker@discoverandchange.com>
            MSG;
            $this->runGit($repoPath, ['commit', '--allow-empty', '-m', $message]);
            $toRev = trim($this->runGit($repoPath, ['rev-parse', 'HEAD']));

            $rendered = (new AcknowledgementsGenerator())->generate($repoPath, $fromRev, $toRev, '8.5.0-test');

            self::assertStringContainsString('- Stephen Nielson (1 commit)', $rendered);
            self::assertStringNotContainsString('Discover and Change AI Worker', $rendered);
            self::assertStringNotContainsString('(2 commits)', $rendered);
        } finally {
            $this->removeRecursive($repoPath);
        }
    }

    public function testEndToEndBradyMillerSelfCoauthorSameCommitCountedOnce(): void
    {
        // Real-world shape from commit 559fbdb04086 (openemr/openemr
        // all-history): Brady Miller is the primary author under his
        // personal email and the Co-authored-by trailer gives his
        // GitHub-noreply identity -- a GitHub-squash-merge artifact
        // on his own PR commits. The two identities must fold into
        // one row counted once per commit, not twice.
        $input = "559fbdb0\tbrady.g.miller@gmail.com\tBrady Miller"
            . "\tbradymiller <278968+bradymiller@users.noreply.github.com>\n";
        $generator = new AcknowledgementsGenerator();

        $commits = $generator->parseLogOutput($input);
        $canonical = $generator->canonicalizeIdentities($commits);
        $deduped = $generator->deduplicatePerCommit($canonical);
        $grouped = $generator->groupByEmail($deduped);

        self::assertSame(
            [['name' => 'Brady Miller', 'commits' => 1]],
            $grouped,
        );
    }

    public function testEndToEndCraigAllenSelfCoauthorSameCommitCountedOnce(): void
    {
        // Real-world shape from commit 0d7b4c7737 (v8_4_0..HEAD):
        // craigrallen (via GitHub noreply) is primary; a Co-authored-by
        // trailer gives his personal-email identity. Both alias to the
        // same canonical row. Pre-fix this counted 2 for the commit;
        // post-fix counts 1.
        $input = "0d7b4c7737\t56693437+craigrallen@users.noreply.github.com\tcraigrallen"
            . "\tCraig Allen <craig@interconnected.au>\n";
        $generator = new AcknowledgementsGenerator();

        $commits = $generator->parseLogOutput($input);
        $canonical = $generator->canonicalizeIdentities($commits);
        $deduped = $generator->deduplicatePerCommit($canonical);
        $grouped = $generator->groupByEmail($deduped);

        self::assertSame(
            [['name' => 'Craig Allen', 'commits' => 1]],
            $grouped,
        );
    }

    public function testEndToEndPipelineMatchesFixtureSnapshot(): void
    {
        // Full pipeline: parseLogOutput -> deduplicatePerCommit ->
        // groupByEmail -> filterAutomated -> render. Uses 8.2.0 (the
        // most recent stable release) as the version label; range
        // name reflects the real prev-release resolution:
        // openemr/openemr's BranchVersionResolver walks past any
        // version missing from website-openemr's data/releases.json,
        // and 8.1.0 (cut then skipped) is intentionally absent from
        // that manifest -- so 8.2.0's actual acknowledgements range
        // is v8_0_0..v8_2_0, not v8_1_0..v8_2_0.
        $generator = new AcknowledgementsGenerator();
        $commits = $generator->parseLogOutput(self::loadFixture('log-8.0.0-to-8.2.0.txt'));
        $deduped = $generator->deduplicatePerCommit($commits);
        $grouped = $generator->groupByEmail($deduped);
        $rendered = $generator->render($generator->filterAutomatedAuthors($grouped), '8.2.0');

        self::assertStringEqualsFile(self::FIXTURE_DIR . '/expected-8.2.0.md', $rendered);
    }

    public function testRenderIsDeterministic(): void
    {
        $generator = new AcknowledgementsGenerator();
        $commits = $generator->parseLogOutput(self::loadFixture('log-8.0.0-to-8.2.0.txt'));
        $deduped = $generator->deduplicatePerCommit($commits);
        $authors = $generator->filterAutomatedAuthors($generator->groupByEmail($deduped));

        self::assertSame($generator->render($authors, '8.2.0'), $generator->render($authors, '8.2.0'));
    }

    public function testSingleCommitAuthorUsesSingularNoun(): void
    {
        $rendered = (new AcknowledgementsGenerator())->render(
            [['name' => 'Lone Contributor', 'commits' => 1]],
            '8.2.0',
        );

        self::assertStringContainsString('- Lone Contributor (1 commit)', $rendered);
        self::assertStringNotContainsString('(1 commits)', $rendered);
    }

    private static function loadFixture(string $name): string
    {
        $contents = file_get_contents(self::FIXTURE_DIR . '/' . $name);
        if ($contents === false) {
            throw new RuntimeException("Fixture not readable: $name");
        }

        return $contents;
    }
}
