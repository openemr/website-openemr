<?php

declare(strict_types=1);

namespace OpenEMR\ReleaseDocs\Tests;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use OpenEMR\ReleaseDocs\ReleaseNotesGenerator;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ReleaseNotesGeneratorTest extends TestCase
{
    private const FIXTURE_DIR = __DIR__ . '/fixtures/release-notes';

    public function testParseItemsExtractsPullRequestData(): void
    {
        $generator = new ReleaseNotesGenerator(self::clientReturning('{}'));

        $prs = $generator->parseItems([
            [
                'number' => 7,
                'title' => 'feat: add thing',
                'html_url' => 'https://example.test/7',
                'user' => ['login' => 'alice'],
            ],
        ]);

        self::assertSame(
            [
                [
                    'number' => 7,
                    'title' => 'feat: add thing',
                    'url' => 'https://example.test/7',
                    'author' => 'alice',
                ],
            ],
            $prs,
        );
    }

    public function testParseItemsRejectsMissingNumber(): void
    {
        $generator = new ReleaseNotesGenerator(self::clientReturning('{}'));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('PR field "number" missing or not int');

        $generator->parseItems([['title' => 'x', 'html_url' => 'x', 'user' => ['login' => 'x']]]);
    }

    public function testGroupByPrefixHandlesScopedAndBreakingPrefixes(): void
    {
        $generator = new ReleaseNotesGenerator(self::clientReturning('{}'));

        $groups = $generator->groupByPrefix([
            self::pr(1, 'feat(api): scoped feature'),
            self::pr(2, 'feat!: breaking feature'),
            self::pr(3, 'fix(ui): scoped fix'),
            self::pr(4, 'BUG: uppercase still groups'),
        ]);

        self::assertCount(2, $groups['Features']);
        self::assertCount(2, $groups['Bug Fixes']);
        self::assertArrayNotHasKey('Other', $groups);
    }

    public function testGroupByPrefixDropsEmptyBuckets(): void
    {
        $generator = new ReleaseNotesGenerator(self::clientReturning('{}'));

        $groups = $generator->groupByPrefix([self::pr(1, 'feat: only one bucket')]);

        self::assertSame(['Features'], array_keys($groups));
    }

    public function testGroupByPrefixRoutesUnknownPrefixesToOther(): void
    {
        $generator = new ReleaseNotesGenerator(self::clientReturning('{}'));

        $groups = $generator->groupByPrefix([
            self::pr(1, 'docs: not in the bucket list'),
            self::pr(2, 'no prefix at all'),
        ]);

        self::assertSame(['Other'], array_keys($groups));
        self::assertCount(2, $groups['Other']);
    }

    public function testFilterNoiseDropsMachineryAndKeepsRealChanges(): void
    {
        $generator = new ReleaseNotesGenerator(self::clientReturning('{}'));

        $kept = $generator->filterNoise([
            self::authored(1, 'feat(api): real feature', 'alice'),
            self::authored(2, 'chore(release): prep 8.1.0', 'openemr-release-bot[bot]'),
            self::authored(3, 'feat: experimental [TEST] thing', 'bob'),
            self::authored(4, 'chore(deps): bump redis from 8.8.0 to 8.8.0', 'dependabot[bot]'),
            self::authored(5, 'chore(deps): bump twig/twig from 3.25.0 to 3.26.0', 'dependabot[bot]'),
            self::authored(6, 'fix(release): widen dispatch branch pattern', 'carol'),
            self::authored(7, 'fix(release-prep): stop bumping docker-version files', 'carol'),
            self::authored(8, 'fix(encounter): handle null uuid (backport #999)', 'carol'),
            self::authored(9, 'chore: release 8.1.0 misc', 'dave'),
            self::authored(10, 'fix(ui): keep me', 'erin'),
        ]);

        // Kept: real feature, real (different-version) dep bump, real fix.
        // Dropped: release bot, [TEST], no-op bump, release/release-prep
        // scopes, backport duplicate, and the "chore: release" straggler.
        self::assertSame([1, 5, 10], array_column($kept, 'number'));
    }

    public function testFilterNoiseDropsDependabotDockerBumpsByPath(): void
    {
        $generator = new ReleaseNotesGenerator(self::clientReturning('{}'));

        $bot = 'dependabot[bot]';
        $dockerPath = 'chore(deps): bump axllent/mailpit from v1.30.2 to v1.30.3'
            . ' in /docker/development-insane in the mailpit group across 1 directory';
        $ciPath = 'chore(deps): bump axllent/mailpit from v1.30.2 to v1.30.3'
            . ' in /ci/compose-shared-mailpit in the mailpit group across 1 directory';
        $composerPath = 'chore(deps): bump guzzlehttp/psr7 from 2.11.0 to 2.12.1 in /tools/release-docs';
        $npmPath = 'chore(deps): bump globals from 17.6.0 to 17.7.0 in /ccdaservice';

        // Path-embedded ecosystem — dependabot puts the target directory
        // in the title. Anything under /docker/... or /ci/... is a
        // docker-compose ecosystem bump, not a package the released
        // OpenEMR ships with.
        $kept = $generator->filterNoise([
            self::authored(1, $dockerPath, $bot),
            self::authored(2, $ciPath, $bot),
            self::authored(3, $composerPath, $bot),
            self::authored(4, $npmPath, $bot),
        ]);

        // Kept: composer bump in /tools/release-docs, npm bump in /ccdaservice.
        // Dropped: docker bumps in /docker/... and /ci/...
        self::assertSame([3, 4], array_column($kept, 'number'));
    }

    public function testFilterNoiseDropsDependabotDockerBumpsByGroupName(): void
    {
        $generator = new ReleaseNotesGenerator(self::clientReturning('{}'));

        $bot = 'dependabot[bot]';

        // Grouped bumps have no path in the title but name the group.
        // Docker-compose groups (per openemr/openemr's dependabot.yml)
        // are the DEPENDABOT_DOCKER_GROUPS list.
        $kept = $generator->filterNoise([
            self::authored(1, 'chore(deps): bump the openemr-images group across 19 directories with 1 update', $bot),
            self::authored(2, 'chore(deps): bump the mariadb group across 4 directories with 1 update', $bot),
            self::authored(3, 'chore(deps): bump the phpmyadmin group across 3 directories with 1 update', $bot),
            self::authored(4, 'chore(deps): bump the mailpit group across 2 directories with 1 update', $bot),
            self::authored(5, 'chore(deps): bump the symfony group with 11 updates', $bot),
            self::authored(6, 'chore(deps-dev): bump webpack from 5.107.2 to 5.108.1 in the build-tools group', $bot),
        ]);

        // Kept: symfony (composer group), build-tools (npm group).
        // Dropped: docker-compose groups.
        self::assertSame([5, 6], array_column($kept, 'number'));
    }

    public function testFilterNoiseDropsReservedWordBotButOnlyByStandardRules(): void
    {
        $generator = new ReleaseNotesGenerator(self::clientReturning('{}'));

        // openemr-reserved-word-bot[bot] is uncommon and its output is a
        // legitimate chore that belongs in release notes — it isn't
        // subject to a bot-specific always-drop rule the way the release
        // bot is. It goes through the standard title-based filters like
        // any other author.
        $kept = $generator->filterNoise([
            self::authored(1, 'chore(deps): refresh reserved-words list', 'openemr-reserved-word-bot[bot]'),
            self::authored(2, 'chore: release 8.1.0 misc', 'openemr-reserved-word-bot[bot]'),
        ]);

        // Kept: legitimate chore.
        // Dropped: matches the "chore: release" straggler pattern.
        self::assertSame([1], array_column($kept, 'number'));
    }

    public function testFullPipelineMatchesFixtureSnapshot(): void
    {
        $body = self::loadFixture('api-page1.json');
        $generator = new ReleaseNotesGenerator(self::clientReturning($body));

        $rendered = $generator->generate('openemr', 'openemr', '2026-02-13', '2026-04-29', '8.1.0');

        self::assertStringEqualsFile(self::FIXTURE_DIR . '/expected-8.1.0.md', $rendered);
    }

    public function testRenderIsDeterministic(): void
    {
        $body = self::loadFixture('api-page1.json');
        $first = (new ReleaseNotesGenerator(self::clientReturning($body)))
            ->generate('openemr', 'openemr', '2026-02-13', '2026-04-29', '8.1.0');
        $second = (new ReleaseNotesGenerator(self::clientReturning($body)))
            ->generate('openemr', 'openemr', '2026-02-13', '2026-04-29', '8.1.0');

        self::assertSame($first, $second);
    }

    public function testFetchMergedPullRequestsPaginates(): void
    {
        $page1Items = [];
        for ($i = 1; $i <= 100; $i++) {
            $page1Items[] = self::apiItem($i, 'feat: pr ' . $i, '2026-04-29T12:00:00Z');
        }
        $page2Items = [
            self::apiItem(101, 'fix: pr 101', '2026-04-28T12:00:00Z'),
            self::apiItem(102, 'chore: pr 102', '2026-04-27T12:00:00Z'),
        ];
        $page1 = json_encode($page1Items, JSON_THROW_ON_ERROR);
        $page2 = json_encode($page2Items, JSON_THROW_ON_ERROR);

        $mock = new MockHandler([new Response(200, [], $page1), new Response(200, [], $page2)]);
        $client = new Client(['handler' => HandlerStack::create($mock)]);

        $prs = (new ReleaseNotesGenerator($client))
            ->fetchMergedPullRequests('openemr', 'openemr', '2026-02-13', '2026-04-29');

        self::assertCount(102, $prs);
        self::assertSame(1, $prs[0]['number']);
        self::assertSame(102, $prs[101]['number']);
    }

    public function testFetchMergedPullRequestsStopsAtWindowBoundary(): void
    {
        $page1 = json_encode([
            self::apiItem(10, 'feat: in window', '2026-04-20T12:00:00Z'),
            self::apiItem(11, 'fix: in window edge', '2026-02-13T00:00:01Z'),
            self::apiItem(12, 'chore: before window', '2026-02-01T12:00:00Z'),
        ], JSON_THROW_ON_ERROR);

        // Second response is queued to prove early-exit doesn't fetch it.
        $mock = new MockHandler([
            new Response(200, [], $page1),
            new Response(500, [], 'must not be called'),
        ]);
        $client = new Client(['handler' => HandlerStack::create($mock)]);

        $prs = (new ReleaseNotesGenerator($client))
            ->fetchMergedPullRequests('openemr', 'openemr', '2026-02-13', '2026-04-29');

        self::assertSame([10, 11], array_column($prs, 'number'));
    }

    public function testFetchMergedPullRequestsSkipsClosedWithoutMerge(): void
    {
        $page1 = json_encode([
            [
                'number' => 1,
                'title' => 'feat: merged',
                'html_url' => 'https://example.test/1',
                'user' => ['login' => 'alice'],
                'merged_at' => '2026-04-20T12:00:00Z',
                'updated_at' => '2026-04-20T12:00:00Z',
            ],
            [
                'number' => 2,
                'title' => 'feat: closed without merge',
                'html_url' => 'https://example.test/2',
                'user' => ['login' => 'bob'],
                'merged_at' => null,
                'updated_at' => '2026-04-19T12:00:00Z',
            ],
        ], JSON_THROW_ON_ERROR);

        $mock = new MockHandler([new Response(200, [], $page1)]);
        $client = new Client(['handler' => HandlerStack::create($mock)]);

        $prs = (new ReleaseNotesGenerator($client))
            ->fetchMergedPullRequests('openemr', 'openemr', '2026-02-13', '2026-04-29');

        self::assertSame([1], array_column($prs, 'number'));
    }

    /**
     * @return array{number: int, title: string, url: string, author: string}
     */
    private static function pr(int $number, string $title): array
    {
        return [
            'number' => $number,
            'title' => $title,
            'url' => "https://example.test/$number",
            'author' => 'tester',
        ];
    }

    /**
     * @return array{number: int, title: string, url: string, author: string}
     */
    private static function authored(int $number, string $title, string $author): array
    {
        return [
            'number' => $number,
            'title' => $title,
            'url' => "https://example.test/$number",
            'author' => $author,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function apiItem(int $number, string $title, string $mergedAt): array
    {
        return [
            'number' => $number,
            'title' => $title,
            'html_url' => "https://github.com/openemr/openemr/pull/$number",
            'user' => ['login' => 'tester'],
            'merged_at' => $mergedAt,
            'updated_at' => $mergedAt,
        ];
    }

    private static function clientReturning(string $body): Client
    {
        $mock = new MockHandler([new Response(200, [], $body)]);

        return new Client(['handler' => HandlerStack::create($mock)]);
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
