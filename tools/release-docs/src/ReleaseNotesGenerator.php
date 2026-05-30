<?php

/**
 * Render the per-release release-notes page from merged-PR data.
 *
 * @package   openemr/website-openemr
 * @link      https://www.open-emr.org
 * @author    Michael A. Smith <michael@opencoreemr.com>
 * @copyright Copyright (c) 2026 OpenCoreEMR Inc.
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace OpenEMR\ReleaseDocs;

use DateTimeImmutable;
use DateTimeZone;
use Generator;
use GuzzleHttp\ClientInterface;
use RuntimeException;
use Symfony\Component\Process\Process;

final class ReleaseNotesGenerator
{
    private const PER_PAGE = 100;

    /**
     * The bucket name that titles starting with the given lowercase
     * Conventional-Commits-style prefix are routed to. Any prefix not
     * listed here (or any title without a prefix at all) is grouped
     * under "Other".
     *
     * @var array<string, string>
     */
    private const PREFIX_BUCKETS = [
        'feat' => 'Features',
        'feature' => 'Features',
        'fix' => 'Bug Fixes',
        'bug' => 'Bug Fixes',
        'bugfix' => 'Bug Fixes',
        'refactor' => 'Refactoring',
        'chore' => 'Chores',
    ];

    /**
     * The order in which non-empty buckets render in the output.
     *
     * @var list<string>
     */
    private const BUCKET_ORDER = [
        'Features',
        'Bug Fixes',
        'Refactoring',
        'Chores',
        'Other',
    ];

    public function __construct(
        private readonly ClientInterface $client,
    ) {
    }

    public function generate(string $owner, string $repo, string $from, string $to, string $version): string
    {
        return $this->render($this->groupByPrefix($this->fetchMergedPullRequests($owner, $repo, $from, $to)), $version);
    }

    /**
     * Date window between the previous release tag's commit date and today
     * (UTC). Falls back to "180 days ago" when the tag is unknown — happens
     * the very first time we see a new minor line.
     *
     * @return array{from: string, to: string}
     */
    public static function dateWindow(string $repoPath, string $prevVersion): array
    {
        $tag = AcknowledgementsGenerator::tagForVersion($prevVersion);
        $today = (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d');

        $process = new Process(['git', '-C', $repoPath, 'log', '-1', '--format=%cs', $tag]);
        $process->run();
        if ($process->isSuccessful()) {
            $from = trim($process->getOutput());
            if ($from !== '') {
                return ['from' => $from, 'to' => $today];
            }
        }

        $fallback = (new DateTimeImmutable('-180 days', new DateTimeZone('UTC')))->format('Y-m-d');
        return ['from' => $fallback, 'to' => $today];
    }

    /**
     * @return list<array{number: int, title: string, url: string, author: string}>
     */
    public function fetchMergedPullRequests(string $owner, string $repo, string $from, string $to): array
    {
        // List-pulls is used in preference to /search/issues because the
        // Search API caps results at 1000 — OpenEMR routinely exceeds
        // that in a release window. /repos/{o}/{r}/pulls has no such cap.
        $fromTs = self::parseBoundary($from, startOfDay: true);
        $toTs = self::parseBoundary($to, startOfDay: false);
        $items = [];
        foreach ($this->fetchClosedPullPages($owner, $repo) as $page) {
            foreach ($page as $pull) {
                $updatedAt = self::parseTimestamp($pull, 'updated_at');
                if ($updatedAt < $fromTs) {
                    // Sorted by updated desc — anything past this point
                    // updated before our window can't have merged in it.
                    return $this->parseItems($items);
                }
                $mergedAtRaw = $pull['merged_at'] ?? null;
                if (!is_string($mergedAtRaw) || $mergedAtRaw === '') {
                    continue;
                }
                $mergedAt = strtotime($mergedAtRaw);
                if ($mergedAt === false || $mergedAt < $fromTs || $mergedAt > $toTs) {
                    continue;
                }
                $items[] = $pull;
            }
        }

        return $this->parseItems($items);
    }

    /**
     * Yield pages of closed PRs sorted by updated_at desc until a short
     * page signals the end.
     *
     * @return Generator<int, list<array<int|string, mixed>>>
     */
    private function fetchClosedPullPages(string $owner, string $repo): Generator
    {
        for ($page = 1;; $page++) {
            $batch = $this->fetchClosedPullPage($owner, $repo, $page);
            yield $batch;
            if (count($batch) < self::PER_PAGE) {
                return;
            }
        }
    }

    /**
     * @return list<array<int|string, mixed>>
     */
    private function fetchClosedPullPage(string $owner, string $repo, int $page): array
    {
        $response = $this->client->request('GET', "/repos/$owner/$repo/pulls", [
            'query' => [
                'state' => 'closed',
                'sort' => 'updated',
                'direction' => 'desc',
                'per_page' => self::PER_PAGE,
                'page' => $page,
            ],
        ]);
        $body = (string) $response->getBody();
        $payload = json_decode($body, true, flags: JSON_THROW_ON_ERROR);
        if (!is_array($payload)) {
            throw new RuntimeException('GitHub pulls API returned a non-array payload');
        }

        $normalized = [];
        foreach ($payload as $item) {
            if (!is_array($item)) {
                throw new RuntimeException('GitHub pulls API item is not an array');
            }
            $normalized[] = $item;
        }

        return $normalized;
    }

    private static function parseBoundary(string $date, bool $startOfDay): int
    {
        $suffix = $startOfDay ? 'T00:00:00Z' : 'T23:59:59Z';
        $ts = strtotime($date . $suffix);
        if ($ts === false) {
            throw new RuntimeException("Invalid date boundary: $date");
        }

        return $ts;
    }

    /**
     * @param array<int|string, mixed> $pull
     */
    private static function parseTimestamp(array $pull, string $field): int
    {
        $raw = $pull[$field] ?? null;
        if (!is_string($raw) || $raw === '') {
            throw new RuntimeException("PR field \"$field\" missing or not non-empty string");
        }
        $ts = strtotime($raw);
        if ($ts === false) {
            throw new RuntimeException("PR field \"$field\" not a parseable timestamp: $raw");
        }

        return $ts;
    }

    /**
     * @param list<array<int|string, mixed>> $items
     * @return list<array{number: int, title: string, url: string, author: string}>
     */
    public function parseItems(array $items): array
    {
        $result = [];
        foreach ($items as $item) {
            $number = $item['number'] ?? null;
            if (!is_int($number)) {
                throw new RuntimeException('PR field "number" missing or not int');
            }
            $title = $item['title'] ?? null;
            if (!is_string($title) || $title === '') {
                throw new RuntimeException('PR field "title" missing or not non-empty string');
            }
            $url = $item['html_url'] ?? null;
            if (!is_string($url) || $url === '') {
                throw new RuntimeException('PR field "html_url" missing or not non-empty string');
            }
            $user = $item['user'] ?? null;
            if (!is_array($user)) {
                throw new RuntimeException('PR field "user" missing or not array');
            }
            $login = $user['login'] ?? null;
            if (!is_string($login) || $login === '') {
                throw new RuntimeException('PR field "user.login" missing or not non-empty string');
            }
            $result[] = [
                'number' => $number,
                'title' => $title,
                'url' => $url,
                'author' => $login,
            ];
        }

        return $result;
    }

    /**
     * @param list<array{number: int, title: string, url: string, author: string}> $prs
     * @return array<string, list<array{number: int, title: string, url: string, author: string}>>
     */
    public function groupByPrefix(array $prs): array
    {
        $groups = [];
        foreach (self::BUCKET_ORDER as $bucket) {
            $groups[$bucket] = [];
        }
        foreach ($prs as $pr) {
            $groups[self::bucketFor($pr['title'])][] = $pr;
        }

        return array_filter($groups, static fn (array $items): bool => $items !== []);
    }

    private static function bucketFor(string $title): string
    {
        $matches = [];
        if (preg_match('/^(\w+)(?:\([^)]*\))?!?:/', $title, $matches) !== 1) {
            return 'Other';
        }

        return self::PREFIX_BUCKETS[strtolower($matches[1])] ?? 'Other';
    }

    /**
     * @param array<string, list<array{number: int, title: string, url: string, author: string}>> $groups
     */
    public function render(array $groups, string $version): string
    {
        $lines = [
            '---',
            sprintf('title: "OpenEMR %s Release Notes"', $version),
            sprintf('version: "%s"', $version),
            '---',
            '',
            sprintf('{{< release-status version="%s" >}}', $version),
            '',
            sprintf('# OpenEMR %s — Release Notes', $version),
            '',
        ];
        foreach ($groups as $heading => $prs) {
            $lines[] = sprintf('## %s', $heading);
            $lines[] = '';
            foreach ($prs as $pr) {
                $lines[] = sprintf(
                    '- [#%d](%s) %s — @%s',
                    $pr['number'],
                    $pr['url'],
                    $pr['title'],
                    $pr['author'],
                );
            }
            $lines[] = '';
        }

        return implode("\n", $lines) . "\n";
    }
}
