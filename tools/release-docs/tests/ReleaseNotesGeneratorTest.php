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
