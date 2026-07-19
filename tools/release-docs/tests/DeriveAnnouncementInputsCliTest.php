<?php

/**
 * @package   openemr/website-openemr
 * @link      https://www.open-emr.org
 * @author    Michael A. Smith <michael@opencoreemr.com>
 * @copyright Copyright (c) 2026 OpenCoreEMR Inc.
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace OpenEMR\ReleaseDocs\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

/**
 * The release-announcements workflow appends derive-announcement-inputs.php
 * stdout directly to $GITHUB_OUTPUT. Errors must therefore not leak into
 * stdout, or a failing run will write `<error>...</error>` lines into the
 * runner's output file and trigger an "Invalid format" step failure that
 * obscures the real error.
 */
final class DeriveAnnouncementInputsCliTest extends TestCase
{
    private const BIN = __DIR__ . '/../bin/derive-announcement-inputs.php';

    public function testHeadRefDrivesAllThreeValuesCanonically(): void
    {
        $process = new Process([
            'php',
            self::BIN,
            '--head-ref=release-docs/8.2.0',
            '--forum-url=https://community.open-emr.org/t/thread/123',
        ]);
        $process->run();

        self::assertSame(0, $process->getExitCode(), 'expected success exit code');
        self::assertSame('', $process->getErrorOutput(), 'no stderr on success');
        self::assertSame(
            "version=8.2.0\ntag=v8_2_0\nbranch=rel-820\nforum_url=https://community.open-emr.org/t/thread/123\n",
            $process->getOutput(),
        );
    }

    public function testHeadRefWithMissingForumUrlEmitsEmptyForumUrl(): void
    {
        $process = new Process(['php', self::BIN, '--head-ref=release-docs/8.1.0']);
        $process->run();

        self::assertSame(0, $process->getExitCode());
        self::assertSame(
            "version=8.1.0\ntag=v8_1_0\nbranch=rel-810\nforum_url=\n",
            $process->getOutput(),
        );
    }

    public function testExplicitFlagsWriteKeyValueLinesToStdoutOnly(): void
    {
        $process = new Process([
            'php',
            self::BIN,
            '--release-version=8.1.0',
            '--release-tag=v8_1_0',
            '--release-branch=rel-810',
            '--forum-url=https://example.com/thread',
        ]);
        $process->run();

        self::assertSame(0, $process->getExitCode(), 'expected success exit code');
        self::assertSame('', $process->getErrorOutput(), 'no stderr on success');
        self::assertSame(
            "version=8.1.0\ntag=v8_1_0\nbranch=rel-810\nforum_url=https://example.com/thread\n",
            $process->getOutput(),
        );
    }

    public function testValidationErrorsGoToStderrAndStdoutStaysEmpty(): void
    {
        $process = new Process([
            'php',
            self::BIN,
            '--release-version=8.1.0',
            '--release-tag=BAD',
            '--release-branch=rel-810',
        ]);
        $process->run();

        self::assertSame(1, $process->getExitCode(), 'expected failure exit code');
        self::assertSame('', $process->getOutput(), 'stdout must stay empty so $GITHUB_OUTPUT is not corrupted');
        self::assertStringContainsString('field tag does not match expected shape', $process->getErrorOutput());
    }

    public function testInvalidHeadRefRejected(): void
    {
        $process = new Process([
            'php',
            self::BIN,
            '--head-ref=some-other-branch',
        ]);
        $process->run();

        self::assertSame(1, $process->getExitCode());
        self::assertSame('', $process->getOutput(), 'stdout must stay empty on error');
        self::assertStringContainsString("must start with 'release-docs/'", $process->getErrorOutput());
    }

    public function testMalformedVersionInHeadRefRejected(): void
    {
        $process = new Process([
            'php',
            self::BIN,
            '--head-ref=release-docs/not-a-version',
        ]);
        $process->run();

        self::assertSame(1, $process->getExitCode());
        self::assertSame('', $process->getOutput());
        self::assertStringContainsString('version parsed from --head-ref does not match', $process->getErrorOutput());
    }

    public function testMissingRequiredFlagsGoToStderr(): void
    {
        $process = new Process(['php', self::BIN]);
        $process->run();

        self::assertSame(1, $process->getExitCode());
        self::assertSame('', $process->getOutput());
        self::assertStringContainsString('Provide either --head-ref', $process->getErrorOutput());
    }

    public function testMutuallyExclusiveSourcesRejected(): void
    {
        $process = new Process([
            'php',
            self::BIN,
            '--head-ref=release-docs/8.1.0',
            '--release-version=8.1.0',
        ]);
        $process->run();

        self::assertSame(1, $process->getExitCode());
        self::assertSame('', $process->getOutput());
        self::assertStringContainsString('mutually exclusive', $process->getErrorOutput());
    }

    public function testInternallyInconsistentExplicitFlagsRejected(): void
    {
        // Each field is shape-valid in isolation, but the tag/branch don't
        // describe the version. Without the cross-consistency check the
        // script would emit mismatched links + a workflow_dispatch typo
        // could silently ship broken announcements.
        $process = new Process([
            'php',
            self::BIN,
            '--release-version=8.1.0',
            '--release-tag=v9_9_9',
            '--release-branch=rel-990',
        ]);
        $process->run();

        self::assertSame(1, $process->getExitCode(), 'expected consistency check to reject');
        self::assertSame('', $process->getOutput());
        self::assertStringContainsString('tag/branch do not match version', $process->getErrorOutput());
    }

    public function testForumUrlWithNewlineRejected(): void
    {
        // Guard against $GITHUB_OUTPUT injection: a CR/LF-containing value
        // emitted verbatim to stdout would open additional key=value lines
        // and let a caller define arbitrary extra workflow outputs.
        $process = new Process([
            'php',
            self::BIN,
            '--head-ref=release-docs/8.1.0',
            "--forum-url=https://good.example\nEVIL_OUTPUT=bad",
        ]);
        $process->run();

        self::assertSame(1, $process->getExitCode());
        self::assertSame(
            '',
            $process->getOutput(),
            'stdout must stay clean so no injected line reaches $GITHUB_OUTPUT',
        );
        self::assertStringContainsString('single-line value', $process->getErrorOutput());
    }
}
