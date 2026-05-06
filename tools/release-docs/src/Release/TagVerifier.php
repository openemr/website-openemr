<?php

/**
 * Verify a release tag against its expected sha and signature posture.
 *
 * Vendored copy of the canonical signature in openemr/openemr-devops#664;
 * the conductor PR will replace this with a shared package.
 *
 * @package   openemr/website-openemr
 * @link      https://www.open-emr.org
 * @author    Michael A. Smith <michael@opencoreemr.com>
 * @copyright Copyright (c) 2026 OpenCoreEMR Inc.
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace OpenEMR\ReleaseDocs\Release;

use Symfony\Component\Process\Process;

final class TagVerifier
{
    private const SHA_PATTERN = '/[0-9a-f]{40}/';
    private const ISO_DATE_PATTERN = '/\d{4}-\d{2}-\d{2}/';

    public function __construct(private readonly string $repoPath)
    {
    }

    public function verify(string $tagName, string $expectedVersion): VerificationResult
    {
        $errors = [];

        $type = $this->git(['cat-file', '-t', $tagName]);
        if ($type === null) {
            $errors[] = sprintf("tag '%s' not found in repo at %s", $tagName, $this->repoPath);
            return new VerificationResult(false, $errors);
        }

        $type = trim($type);
        if ($type !== 'tag') {
            $errors[] = sprintf(
                "tag '%s' is not annotated (object type '%s', expected 'tag')",
                $tagName,
                $type,
            );
        }

        $raw = $this->git(['cat-file', '-p', $tagName]);
        if ($raw === null) {
            $errors[] = sprintf("could not read tag object for '%s'", $tagName);
            return new VerificationResult(false, $errors);
        }

        // Skip the headers (object/type/tag/tagger lines) and check the message body only;
        // the SHA pattern would always match the 'object <sha>' header otherwise.
        $parts = preg_split('/\R\R/', $raw, 2);
        $body = is_array($parts) && array_key_exists(1, $parts) ? $parts[1] : '';

        if (!str_contains($body, $expectedVersion)) {
            $errors[] = sprintf("tag message does not contain expected version '%s'", $expectedVersion);
        }

        if (preg_match(self::ISO_DATE_PATTERN, $body) !== 1) {
            $errors[] = 'tag message does not contain an ISO-8601 date (YYYY-MM-DD)';
        }

        if (preg_match(self::SHA_PATTERN, $body) !== 1) {
            $errors[] = 'tag message does not contain a 40-character merge commit SHA';
        }

        return new VerificationResult($errors === [], $errors);
    }

    /**
     * @param list<string> $args
     */
    private function git(array $args): ?string
    {
        $process = new Process(array_merge(['git', '-C', $this->repoPath], $args));
        $process->run();
        if (!$process->isSuccessful()) {
            return null;
        }

        return $process->getOutput();
    }
}
