<?php

/**
 * CLI option helpers for release-docs commands.
 *
 * @package   openemr/website-openemr
 * @link      https://www.open-emr.org
 * @author    Michael A. Smith <michael@opencoreemr.com>
 * @copyright Copyright (c) 2026 OpenCoreEMR Inc.
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace OpenEMR\ReleaseDocs\Cli;

use RuntimeException;
use Symfony\Component\Console\Input\InputInterface;

final class Options
{
    /**
     * Fetch a required string option, asserting it is present and non-empty.
     *
     * @return non-empty-string
     */
    public static function requireString(InputInterface $input, string $name): string
    {
        $value = $input->getOption($name);
        if (!is_string($value) || $value === '') {
            throw new RuntimeException("--$name is required");
        }

        return $value;
    }
}
