<?php

declare(strict_types=1);

namespace OpenEMR\ReleaseDocs\Tests;

use OpenEMR\ReleaseDocs\AcknowledgementsGenerator;
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

    public function testEndToEndPipelineMatchesFixtureSnapshot(): void
    {
        // Full pipeline: parseLogOutput -> groupByEmail -> filterAutomated
        // -> render. Uses 8.2.0 (the most recent stable release) as
        // the version label. 8.1.0 would be ambiguous (that version
        // was cut then skipped, so it's a real-but-nonexistent tag).
        $generator = new AcknowledgementsGenerator();
        $commits = $generator->parseLogOutput(self::loadFixture('log-8.1.0-to-8.2.0.txt'));
        $grouped = $generator->groupByEmail($commits);
        $rendered = $generator->render($generator->filterAutomatedAuthors($grouped), '8.2.0');

        self::assertStringEqualsFile(self::FIXTURE_DIR . '/expected-8.2.0.md', $rendered);
    }

    public function testRenderIsDeterministic(): void
    {
        $generator = new AcknowledgementsGenerator();
        $commits = $generator->parseLogOutput(self::loadFixture('log-8.1.0-to-8.2.0.txt'));
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
