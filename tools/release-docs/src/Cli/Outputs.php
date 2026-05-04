<?php

/**
 * CLI output helpers for release-docs commands.
 *
 * @package   openemr/website-openemr
 * @link      https://www.open-emr.org
 * @author    Michael A. Smith <michael@opencoreemr.com>
 * @copyright Copyright (c) 2026 OpenCoreEMR Inc.
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace OpenEMR\ReleaseDocs\Cli;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;

final class Outputs
{
    /**
     * Write rendered content to a file or stdout.
     *
     * If `$outputPath` is `-`, writes to the console output. Otherwise
     * creates any missing parent directories and writes to the path.
     * Returns a Symfony Console exit code.
     */
    public static function writeOrEcho(OutputInterface $output, string $outputPath, string $rendered): int
    {
        if ($outputPath === '-') {
            $output->write($rendered);
            return Command::SUCCESS;
        }

        $dir = dirname($outputPath);
        if ($dir !== '' && $dir !== '.' && !is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            $output->writeln("<error>Could not create directory $dir</error>");
            return Command::FAILURE;
        }

        if (file_put_contents($outputPath, $rendered) === false) {
            $output->writeln("<error>Could not write to $outputPath</error>");
            return Command::FAILURE;
        }

        $output->writeln("Wrote $outputPath");
        return Command::SUCCESS;
    }
}
