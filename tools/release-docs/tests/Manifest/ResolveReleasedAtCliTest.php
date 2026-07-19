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

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

/**
 * Guards the 4-branch resolution logic in `bin/resolve-released-at.php`.
 * The workflow gathers each source (workflow_dispatch input, Release
 * publishedAt, annotated tag tagger.date, envelope dispatched_at) via
 * inline gh/jq calls and hands the four candidates here to pick the
 * winner. Each branch should be exercised in isolation so a future
 * refactor can't silently invert the priority order or accidentally
 * skip a source.
 *
 * See openemr/website-openemr#145.
 */
final class ResolveReleasedAtCliTest extends TestCase
{
    private const BIN = __DIR__ . '/../../bin/resolve-released-at.php';

    /**
     * @return array<string, array{
     *     input: string,
     *     release: string,
     *     tagger: string,
     *     dispatched: string,
     *     expected: string,
     * }>
     *
     * @codeCoverageIgnore Data providers run before coverage instrumentation starts.
     */
    public static function priorityProvider(): array
    {
        return [
            'input-released-at wins over all other sources' => [
                'input' => '2026-06-01',
                'release' => '2026-06-02',
                'tagger' => '2026-06-03',
                'dispatched' => '2026-06-04',
                'expected' => "released_at=2026-06-01\n",
            ],
            'release-published-at wins when input is empty' => [
                'input' => '',
                'release' => '2026-06-02',
                'tagger' => '2026-06-03',
                'dispatched' => '2026-06-04',
                'expected' => "released_at=2026-06-02\n",
            ],
            'tagger-date wins when input + release are empty' => [
                'input' => '',
                'release' => '',
                'tagger' => '2026-06-03',
                'dispatched' => '2026-06-04',
                'expected' => "released_at=2026-06-03\n",
            ],
            'dispatched-at wins when input + release + tagger are empty' => [
                'input' => '',
                'release' => '',
                'tagger' => '',
                'dispatched' => '2026-06-04',
                'expected' => "released_at=2026-06-04\n",
            ],
        ];
    }

    #[DataProvider('priorityProvider')]
    public function testResolutionPriorityIsInputReleaseTaggerDispatched(
        string $input,
        string $release,
        string $tagger,
        string $dispatched,
        string $expected,
    ): void {
        $process = new Process([
            'php',
            self::BIN,
            '--input-released-at=' . $input,
            '--release-published-at=' . $release,
            '--tagger-date=' . $tagger,
            '--dispatched-at=' . $dispatched,
        ]);
        $process->run();

        self::assertSame(0, $process->getExitCode(), $process->getErrorOutput());
        self::assertSame('', $process->getErrorOutput(), 'nothing should reach stderr on success');
        self::assertSame($expected, $process->getOutput());
    }

    public function testAllEmptyEmitsNothingButReturnsZero(): void
    {
        // When every candidate is empty, stdout stays clean so a
        // downstream `>> $GITHUB_OUTPUT` redirect doesn't corrupt the
        // output file. Update-manifest.php's fallback + stderr warning
        // handles the missing-source case downstream.
        $process = new Process(['php', self::BIN]);
        $process->run();

        self::assertSame(0, $process->getExitCode(), $process->getErrorOutput());
        self::assertSame('', $process->getOutput(), 'stdout must stay empty when all sources are empty');
        self::assertSame('', $process->getErrorOutput());
    }

    public function testWhitespaceOnlyValuesAreTreatedAsEmpty(): void
    {
        // A source that resolves to whitespace (e.g., a failed jq call
        // returning "  ") must not be treated as a valid date and
        // absorbed as the winner. Trim + non-empty check in the CLI.
        $process = new Process([
            'php',
            self::BIN,
            '--input-released-at=   ',
            '--release-published-at=',
            '--tagger-date=2026-06-03',
        ]);
        $process->run();

        self::assertSame(0, $process->getExitCode(), $process->getErrorOutput());
        self::assertSame("released_at=2026-06-03\n", $process->getOutput());
    }
}
