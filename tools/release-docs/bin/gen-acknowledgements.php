#!/usr/bin/env php
<?php

/**
 * Render the Hugo acknowledgements page for an OpenEMR release.
 *
 * Wraps `git shortlog` against an openemr/openemr checkout and writes the
 * grouped author list as a Hugo content file.
 *
 * @package   openemr/website-openemr
 * @link      https://www.open-emr.org
 * @author    Michael A. Smith <michael@opencoreemr.com>
 * @copyright Copyright (c) 2026 OpenCoreEMR Inc.
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

use OpenEMR\ReleaseDocs\AcknowledgementsGenerator;
use OpenEMR\ReleaseDocs\Cli\Options;
use OpenEMR\ReleaseDocs\Cli\Outputs;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

require __DIR__ . '/../vendor/autoload.php';

$command = new class () extends Command {
    protected function configure(): void
    {
        $this
            ->setName('gen-acknowledgements')
            ->setDescription('Render the Hugo acknowledgements page for an OpenEMR release.')
            ->addOption('repo-path', null, InputOption::VALUE_REQUIRED, 'Path to an openemr/openemr checkout')
            ->addOption('prev-version', null, InputOption::VALUE_REQUIRED, 'Previous release version (e.g. 8.0.0)')
            ->addOption('to', null, InputOption::VALUE_REQUIRED, 'Upper revision bound (default HEAD)', 'HEAD')
            ->addOption('release-version', null, InputOption::VALUE_REQUIRED, 'Display version string (e.g. 8.1.0)')
            ->addOption('output', null, InputOption::VALUE_REQUIRED, 'Output path (- for stdout)', '-');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $repoPath = Options::requireString($input, 'repo-path');
        $prevVersion = Options::requireString($input, 'prev-version');
        $version = Options::requireString($input, 'release-version');
        $to = Options::requireString($input, 'to');
        $outputPath = Options::requireString($input, 'output');

        $from = AcknowledgementsGenerator::tagForVersion($prevVersion);
        $rendered = (new AcknowledgementsGenerator())->generate($repoPath, $from, $to, $version);

        return Outputs::writeOrEcho($output, $outputPath, $rendered);
    }
};

$app = new Application('gen-acknowledgements', '0.1.0');
$app->add($command);
$name = $command->getName();
if ($name === null) {
    throw new RuntimeException('Command name not set');
}
$app->setDefaultCommand($name, true);
$app->run();
