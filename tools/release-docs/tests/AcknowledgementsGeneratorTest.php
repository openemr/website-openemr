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

    public function testParseLogOutputExtractsEmailAndName(): void
    {
        $input = "alice@example.com\tAlice Smith\nbob@example.com\tBob Jones\n";

        $commits = (new AcknowledgementsGenerator())->parseLogOutput($input);

        self::assertSame(
            [
                ['email' => 'alice@example.com', 'name' => 'Alice Smith'],
                ['email' => 'bob@example.com', 'name' => 'Bob Jones'],
            ],
            $commits,
        );
    }

    public function testParseLogOutputIgnoresBlankAndMalformedLines(): void
    {
        // Malformed = no tab separator, or blank name/email after trim.
        $input = "alice@example.com\tAlice\n\nnotab-line\n\t\nbob@example.com\tBob\n";

        $commits = (new AcknowledgementsGenerator())->parseLogOutput($input);

        self::assertSame(
            [
                ['email' => 'alice@example.com', 'name' => 'Alice'],
                ['email' => 'bob@example.com', 'name' => 'Bob'],
            ],
            $commits,
        );
    }

    public function testParseLogOutputExtractsCoauthorsFromTrailerField(): void
    {
        // New format: third tab-separated field carries RS-separated
        // Co-authored-by trailer values ("Name <email>" each). Each
        // co-author gets its own record for full per-commit credit.
        $input = "alice@example.com\tAlice Smith\tBob Jones <bob@example.com>\x1eCarla Diaz <carla@example.com>\n";

        $commits = (new AcknowledgementsGenerator())->parseLogOutput($input);

        self::assertSame(
            [
                ['email' => 'alice@example.com', 'name' => 'Alice Smith'],
                ['email' => 'bob@example.com', 'name' => 'Bob Jones'],
                ['email' => 'carla@example.com', 'name' => 'Carla Diaz'],
            ],
            $commits,
        );
    }

    public function testParseLogOutputBackwardCompatibleWithNoTrailerField(): void
    {
        // Lines missing the third field (pre-fix format) still parse
        // as primary-author-only. Guards against accidentally breaking
        // any external caller that fed a two-field log.
        $input = "alice@example.com\tAlice Smith\n";

        $commits = (new AcknowledgementsGenerator())->parseLogOutput($input);

        self::assertSame(
            [['email' => 'alice@example.com', 'name' => 'Alice Smith']],
            $commits,
        );
    }

    public function testParseLogOutputEmptyTrailerFieldEmitsPrimaryAuthorOnly(): void
    {
        // git emits an empty third field when the commit has no
        // Co-authored-by trailers. Line looks like "email\tname\t\n".
        // Primary author still emitted; no co-author records added.
        $input = "alice@example.com\tAlice Smith\t\n";

        $commits = (new AcknowledgementsGenerator())->parseLogOutput($input);

        self::assertSame(
            [['email' => 'alice@example.com', 'name' => 'Alice Smith']],
            $commits,
        );
    }

    public function testParseLogOutputMalformedCoauthorTrailerSkippedNotFatal(): void
    {
        // Trailer syntax expects "Name <email>". A malformed value in
        // history shouldn't crash the page render -- skip that one,
        // keep the rest.
        $input = "alice@example.com\tAlice\tno-brackets-here\x1eBob Jones <bob@example.com>\n";

        $commits = (new AcknowledgementsGenerator())->parseLogOutput($input);

        self::assertSame(
            [
                ['email' => 'alice@example.com', 'name' => 'Alice'],
                ['email' => 'bob@example.com', 'name' => 'Bob Jones'],
            ],
            $commits,
        );
    }

    public function testParseLogOutputCoauthorWithWhitespaceIsTrimmed(): void
    {
        // Trailer values with leading/trailing whitespace still parse
        // cleanly. Also covers the "  <email>" spacing convention.
        $input = "alice@example.com\tAlice\t  Bob Jones   <bob@example.com>  \n";

        $commits = (new AcknowledgementsGenerator())->parseLogOutput($input);

        self::assertSame(
            [
                ['email' => 'alice@example.com', 'name' => 'Alice'],
                ['email' => 'bob@example.com', 'name' => 'Bob Jones'],
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
        $input = "brady@example.com\tBrady Miller\tStephen Waite <stephen@example.com>\n";
        $generator = new AcknowledgementsGenerator();
        $commits = $generator->parseLogOutput($input);
        $grouped = $generator->groupByEmail($commits);

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
            // the assertion below would fail.
            self::assertSame(
                [
                    ['email' => 'primary@example.com', 'name' => 'Primary Author'],
                    ['email' => 'folded@example.com', 'name' => 'Really Long Continuation Name'],
                ],
                $records,
            );
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
            ['email' => '278968+bradymiller@users.noreply.github.com', 'name' => 'bradymiller'],
            ['email' => 'brady.g.miller@gmail.com', 'name' => 'Brady Miller'],
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
            ['email' => 'brady.g.miller@gmail.com', 'name' => 'Brady Miller'],
            ['email' => '278968+bradymiller@users.noreply.github.com', 'name' => 'bradymiller'],
            ['email' => '1566303+kojiromike@users.noreply.github.com', 'name' => 'kojiromike'],
            ['email' => 'jerry@example.com', 'name' => 'Jerry Padgett'],
        ];

        $result = (new AcknowledgementsGenerator($resolver))->canonicalizeGithubNoreplyNames($commits);

        self::assertSame(
            [
                // Primary-author record (non-noreply email) untouched.
                ['email' => 'brady.g.miller@gmail.com', 'name' => 'Brady Miller'],
                // Co-author records (noreply emails) get canonicalized names.
                ['email' => '278968+bradymiller@users.noreply.github.com', 'name' => 'Brady Miller'],
                ['email' => '1566303+kojiromike@users.noreply.github.com', 'name' => 'Michael Smith'],
                // Non-noreply co-author (personal email) untouched.
                ['email' => 'jerry@example.com', 'name' => 'Jerry Padgett'],
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
            ['email' => 'olduser@users.noreply.github.com', 'name' => 'olduser'],
        ];
        $result = (new AcknowledgementsGenerator($resolver))->canonicalizeGithubNoreplyNames($commits);
        self::assertSame(
            [['email' => 'olduser@users.noreply.github.com', 'name' => 'Old User Real Name']],
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

        // Same username appears 5x in different commits.
        $commits = array_fill(
            0,
            5,
            ['email' => '278968+bradymiller@users.noreply.github.com', 'name' => 'bradymiller'],
        );
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
            ['email' => '278968+bradymiller@users.noreply.github.com', 'name' => 'bradymiller'],
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
        // collapses them into one row with the combined count.
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

        // Simulate 6 primary + 6 co-author records for the same person.
        $commits = array_merge(
            array_fill(0, 6, ['email' => 'brady.g.miller@gmail.com', 'name' => 'Brady Miller']),
            array_fill(0, 6, ['email' => '278968+bradymiller@users.noreply.github.com', 'name' => 'bradymiller']),
        );

        $canonicalized = $generator->canonicalizeGithubNoreplyNames($commits);
        $grouped = $generator->groupByEmail($canonicalized);
        $merged = $generator->mergeSameNameEntries($grouped);

        self::assertSame(
            [['name' => 'Brady Miller', 'commits' => 12]],
            $merged,
        );
    }

    public function testEndToEndPipelineMatchesFixtureSnapshot(): void
    {
        // Full pipeline: parseLogOutput -> groupByEmail -> filterAutomated
        // -> render. Uses 8.2.0 (the most recent stable release) as
        // the version label; range name reflects the real prev-release
        // resolution: openemr/openemr's BranchVersionResolver walks
        // past any version missing from website-openemr's
        // data/releases.json, and 8.1.0 (cut then skipped) is
        // intentionally absent from that manifest -- so 8.2.0's actual
        // acknowledgements range is v8_0_0..v8_2_0, not v8_1_0..v8_2_0.
        $generator = new AcknowledgementsGenerator();
        $commits = $generator->parseLogOutput(self::loadFixture('log-8.0.0-to-8.2.0.txt'));
        $grouped = $generator->groupByEmail($commits);
        $rendered = $generator->render($generator->filterAutomatedAuthors($grouped), '8.2.0');

        self::assertStringEqualsFile(self::FIXTURE_DIR . '/expected-8.2.0.md', $rendered);
    }

    public function testRenderIsDeterministic(): void
    {
        $generator = new AcknowledgementsGenerator();
        $commits = $generator->parseLogOutput(self::loadFixture('log-8.0.0-to-8.2.0.txt'));
        $authors = $generator->filterAutomatedAuthors($generator->groupByEmail($commits));

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
