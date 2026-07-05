<?php

declare(strict_types=1);

namespace OpenEMR\ReleaseDocs\Tests;

use OpenEMR\ReleaseDocs\AcknowledgementsGenerator;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class AcknowledgementsGeneratorTest extends TestCase
{
    private const FIXTURE_DIR = __DIR__ . '/fixtures/acknowledgements';

    public function testParseShortlogExtractsAuthorsAndCounts(): void
    {
        $authors = (new AcknowledgementsGenerator())->parseShortlog(self::loadFixture('shortlog-8.0.0-to-8.1.0.txt'));

        self::assertCount(8, $authors);
        self::assertSame(['name' => 'Test Author One', 'commits' => 142], $authors[0]);
        self::assertSame(['name' => 'Test Author Six', 'commits' => 1], $authors[7]);
    }

    public function testParseShortlogIgnoresBlankAndMalformedLines(): void
    {
        $input = "   3  Alice\n\n   not-a-line\n   2  Bob\n";

        $authors = (new AcknowledgementsGenerator())->parseShortlog($input);

        self::assertSame(
            [
                ['name' => 'Alice', 'commits' => 3],
                ['name' => 'Bob', 'commits' => 2],
            ],
            $authors,
        );
    }

    public function testFilterBotsDropsBotAuthorsAndReindexes(): void
    {
        $authors = (new AcknowledgementsGenerator())->filterBots([
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

    public function testFilterBotsOnlyMatchesTrailingBotSuffix(): void
    {
        // A hypothetical human contributor whose display name happens to
        // contain "[bot]" in the middle isn't dropped; only the trailing-
        // suffix pattern (used by GitHub App identities) is filtered.
        $authors = (new AcknowledgementsGenerator())->filterBots([
            ['name' => 'Alice [bot maintainer] Smith', 'commits' => 5],
            ['name' => 'noisy[bot]', 'commits' => 100],
        ]);

        self::assertSame(
            [['name' => 'Alice [bot maintainer] Smith', 'commits' => 5]],
            $authors,
        );
    }

    public function testRenderMatchesFixtureSnapshotAfterBotFilter(): void
    {
        $generator = new AcknowledgementsGenerator();
        $authors = $generator->filterBots(
            $generator->parseShortlog(self::loadFixture('shortlog-8.0.0-to-8.1.0.txt')),
        );

        $rendered = $generator->render($authors, '8.1.0');

        self::assertStringEqualsFile(self::FIXTURE_DIR . '/expected-8.1.0.md', $rendered);
    }

    public function testRenderIsDeterministic(): void
    {
        $generator = new AcknowledgementsGenerator();
        $authors = $generator->filterBots(
            $generator->parseShortlog(self::loadFixture('shortlog-8.0.0-to-8.1.0.txt')),
        );

        self::assertSame($generator->render($authors, '8.1.0'), $generator->render($authors, '8.1.0'));
    }

    public function testSingleCommitAuthorUsesSingularNoun(): void
    {
        $rendered = (new AcknowledgementsGenerator())->render(
            [['name' => 'Lone Contributor', 'commits' => 1]],
            '8.1.0',
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
