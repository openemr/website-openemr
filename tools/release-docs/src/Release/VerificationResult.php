<?php

/**
 * Outcome of a tag-signature verification: success or accumulated errors.
 *
 * @package   openemr/website-openemr
 * @link      https://www.open-emr.org
 * @author    Michael A. Smith <michael@opencoreemr.com>
 * @copyright Copyright (c) 2026 OpenCoreEMR Inc.
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace OpenEMR\ReleaseDocs\Release;

final class VerificationResult
{
    /**
     * @param list<string> $errors
     */
    public function __construct(
        public readonly bool $ok,
        public readonly array $errors,
    ) {
    }
}
