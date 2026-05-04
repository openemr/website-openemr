<?php

declare(strict_types=1);

namespace OpenEMR\ReleaseDocs\Tests\Release;

use OpenEMR\ReleaseDocs\Release\TagVerifier;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

final class TagVerifierTest extends TestCase
{
    private string $repoPath = '';

    protected function setUp(): void
    {
        $this->repoPath = sys_get_temp_dir() . '/release-docs-tag-test-' . bin2hex(random_bytes(8));
        if (!mkdir($this->repoPath, 0755, true)) {
            self::fail('Could not create tmp repo path');
        }

        $this->git(['init', '-q', '-b', 'main']);
        $this->git(['config', 'user.email', 'test@example.invalid']);
        $this->git(['config', 'user.name', 'Test']);
        $this->git(['config', 'commit.gpgsign', 'false']);
        $this->git(['config', 'tag.gpgsign', 'false']);
        $this->git(['commit', '--allow-empty', '-q', '-m', 'initial']);
    }

    protected function tearDown(): void
    {
        (new Process(['rm', '-rf', $this->repoPath]))->mustRun();
    }

    public function testAnnotatedTagWithCompliantMessageVerifies(): void
    {
        $sha = trim($this->git(['rev-parse', 'HEAD']));
        $message = "OpenEMR 8.1.0 released 2026-04-29\n\nMerge commit: $sha";
        $this->git(['tag', '-a', 'v8_1_0', '-m', $message]);

        $result = (new TagVerifier($this->repoPath))->verify('v8_1_0', '8.1.0');

        self::assertSame([], $result->errors);
        self::assertTrue($result->ok);
    }

    public function testLightweightTagIsRejectedAsNotAnnotated(): void
    {
        $this->git(['tag', 'v8_1_0']);

        $result = (new TagVerifier($this->repoPath))->verify('v8_1_0', '8.1.0');

        self::assertFalse($result->ok);
        self::assertContainsMatching($result->errors, 'not annotated');
    }

    public function testMissingExpectedVersionInMessageFails(): void
    {
        $sha = trim($this->git(['rev-parse', 'HEAD']));
        $message = "Some other release notice 2026-04-29\n\nMerge commit: $sha";
        $this->git(['tag', '-a', 'v8_1_0', '-m', $message]);

        $result = (new TagVerifier($this->repoPath))->verify('v8_1_0', '8.1.0');

        self::assertFalse($result->ok);
        self::assertContainsMatching($result->errors, "expected version '8.1.0'");
    }

    public function testMissingDateAndShaInMessageFails(): void
    {
        $this->git(['tag', '-a', 'v8_1_0', '-m', 'OpenEMR 8.1.0 released']);

        $result = (new TagVerifier($this->repoPath))->verify('v8_1_0', '8.1.0');

        self::assertFalse($result->ok);
        self::assertContainsMatching($result->errors, 'ISO-8601 date');
        self::assertContainsMatching($result->errors, '40-character merge commit SHA');
    }

    public function testMissingTagFails(): void
    {
        $result = (new TagVerifier($this->repoPath))->verify('v9_9_9', '9.9.9');

        self::assertFalse($result->ok);
        self::assertContainsMatching($result->errors, 'not found');
    }

    /**
     * @param list<string> $errors
     */
    private static function assertContainsMatching(array $errors, string $needle): void
    {
        $matched = array_values(array_filter(
            $errors,
            static fn(string $err): bool => str_contains($err, $needle),
        ));

        self::assertNotSame(
            [],
            $matched,
            sprintf("No error contained '%s'. Errors: %s", $needle, implode(' | ', $errors)),
        );
    }

    /**
     * @param list<string> $args
     */
    private function git(array $args): string
    {
        $process = new Process(array_merge(['git', '-C', $this->repoPath], $args));
        $process->mustRun();

        return $process->getOutput();
    }
}
