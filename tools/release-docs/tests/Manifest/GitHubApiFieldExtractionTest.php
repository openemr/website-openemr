<?php

/**
 * @package   openemr/website-openemr
 * @link      https://www.open-emr.org
 * @author    Michael A. Smith <michael@opencoreemr.com>
 * @copyright Copyright (c) 2026 OpenCoreEMR Inc.
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace OpenEMR\ReleaseDocs\Tests\Manifest;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

/**
 * Guards the jq-filter expressions the release-docs.yml "Resolve
 * released_at" step uses to extract fields from GitHub API responses.
 *
 * These filters live as inline shell in the workflow (see the
 * `Resolve released_at` step), so a typo or a wrong assumption about
 * the API response shape would otherwise only surface in production
 * on a real openemr-tag dispatch. The fixtures under
 * tests/fixtures/github-api/ are captured verbatim from real gh calls
 * against v8_2_0; regenerate them (see the update block at the bottom
 * of this file) if GitHub's API response shape changes and the
 * assertions here start failing.
 *
 * See openemr/website-openemr#145.
 */
final class GitHubApiFieldExtractionTest extends TestCase
{
    private const FIXTURE_DIR = __DIR__ . '/../fixtures/github-api';

    /**
     * Workflow expression:
     *   gh release view <tag> --repo openemr/openemr --json publishedAt \
     *       --jq '(.publishedAt // "")[0:10]'
     *
     * Must extract a YYYY-MM-DD date string from the `publishedAt`
     * ISO 8601 timestamp on the Release object. This is the preferred
     * source for released_at (matches the user-facing "Released"
     * moment on github.com/openemr/openemr/releases).
     *
     * The `// ""` null-coalesce is critical: without it, a null field
     * or missing field would slice to the literal string "null" (jq's
     * default rendering) rather than an empty string, and the workflow
     * would happily set released_at=null. See openemr/website-openemr#199's
     * rabbit review for the exact failure mode.
     */
    public function testReleasePublishedAtJqExtraction(): void
    {
        $result = $this->runJq('(.publishedAt // "")[0:10]', 'release-view-v8_2_0.json');
        self::assertSame('2026-07-08', $result);
    }

    public function testReleasePublishedAtJqExtractionWithNullField(): void
    {
        $result = $this->runJq('(.publishedAt // "")[0:10]', 'release-view-null-publishedAt.json');
        self::assertSame('', $result, 'null publishedAt must coalesce to "" so downstream bash sees empty');
    }

    /**
     * Workflow expression (step 1 of the annotated-tag lookup):
     *   gh api "repos/openemr/openemr/git/refs/tags/<tag>" --jq '.object.sha // ""'
     *
     * Must extract the tag object SHA from the refs/tags response;
     * feeds into step 2 (`git/tags/<sha>`) below. Same null-guard
     * as the other extractions.
     */
    public function testAnnotatedTagObjectShaJqExtraction(): void
    {
        $result = $this->runJq('.object.sha // ""', 'refs-tags-v8_2_0.json');
        self::assertMatchesRegularExpression(
            '/^[0-9a-f]{40}$/',
            $result,
            'refs/tags response must yield a 40-hex tag object SHA at .object.sha',
        );
    }

    /**
     * Workflow expression (step 2 of the annotated-tag lookup):
     *   gh api "repos/openemr/openemr/git/tags/<sha>" \
     *       --jq '(.tagger.date // "")[0:10]'
     *
     * Must extract a YYYY-MM-DD date string from the annotated tag's
     * `tagger.date` ISO 8601 timestamp. This is the 3rd-priority
     * source for released_at -- authoritative and atomic with tag
     * creation, always available for openemr-tag events.
     */
    public function testAnnotatedTagTaggerDateJqExtraction(): void
    {
        $result = $this->runJq('(.tagger.date // "")[0:10]', 'git-tags-v8_2_0.json');
        self::assertSame('2026-07-08', $result);
    }

    public function testAnnotatedTagTaggerDateJqExtractionWithNullField(): void
    {
        $result = $this->runJq('(.tagger.date // "")[0:10]', 'git-tags-null-tagger-date.json');
        self::assertSame('', $result, 'null tagger.date must coalesce to "" so downstream bash sees empty');
    }

    /**
     * The refs/tags response's `.object.sha` must round-trip: the
     * SHA we extract in step 1 must be a valid input to step 2's
     * `git/tags/<sha>` call. Guards against a schema-drift scenario
     * where the two APIs disagree on the tag object identifier.
     */
    public function testRefsTagsShaMatchesGitTagsShaField(): void
    {
        $refsSha = $this->runJq('.object.sha', 'refs-tags-v8_2_0.json');
        $gitTagsSha = $this->runJq('.sha', 'git-tags-v8_2_0.json');
        self::assertSame($refsSha, $gitTagsSha);
    }

    private function runJq(string $filter, string $fixtureName): string
    {
        $fixturePath = self::FIXTURE_DIR . '/' . $fixtureName;
        $fixtureContent = file_get_contents($fixturePath);
        self::assertIsString($fixtureContent, "fixture unreadable: {$fixturePath}");

        $process = new Process(['jq', '-r', $filter]);
        $process->setInput($fixtureContent);
        $process->mustRun();
        return trim($process->getOutput());
    }

    // Regenerate fixtures (one-shot maintenance command):
    //
    //   gh release view v8_2_0 --repo openemr/openemr --json publishedAt \
    //       > tests/fixtures/github-api/release-view-v8_2_0.json
    //   gh api repos/openemr/openemr/git/refs/tags/v8_2_0 \
    //       > tests/fixtures/github-api/refs-tags-v8_2_0.json
    //   tag_sha=$(gh api repos/openemr/openemr/git/refs/tags/v8_2_0 --jq '.object.sha')
    //   gh api "repos/openemr/openemr/git/tags/$tag_sha" \
    //       > tests/fixtures/github-api/git-tags-v8_2_0.json
}
