#!/usr/bin/env php
<?php

/**
 * Render the Hugo install or upgrade page for an OpenEMR release.
 *
 * Driven by templates under `tools/release-docs/templates/`; the version
 * and minimum PHP requirement are filled in for the named release.
 *
 * @package   openemr/website-openemr
 * @link      https://www.open-emr.org
 * @author    Michael A. Smith <michael@opencoreemr.com>
 * @copyright Copyright (c) 2026 OpenCoreEMR Inc.
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

use OpenEMR\ReleaseDocs\Cli\Options;
use OpenEMR\ReleaseDocs\Cli\Outputs;
use OpenEMR\ReleaseDocs\InstallPagesGenerator;
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
            ->setName('gen-install-pages')
            ->setDescription('Render the Hugo install or upgrade page for an OpenEMR release.')
            ->addOption('mode', null, InputOption::VALUE_REQUIRED, 'install or upgrade', 'install')
            ->addOption('release-version', null, InputOption::VALUE_REQUIRED, 'Release version (e.g. 8.1.0)')
            ->addOption('previous-version', null, InputOption::VALUE_REQUIRED, 'Previous version (upgrade mode only)')
            ->addOption(
                'min-php',
                null,
                InputOption::VALUE_REQUIRED,
                'Minimum PHP version',
                InstallPagesGenerator::DEFAULT_MIN_PHP,
            )
            ->addOption(
                'template-dir',
                null,
                InputOption::VALUE_REQUIRED,
                'Template directory',
                __DIR__ . '/../templates',
            )
            ->addOption('output', null, InputOption::VALUE_REQUIRED, 'Output path (- for stdout)', '-');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $mode = Options::requireString($input, 'mode');
        $version = Options::requireString($input, 'release-version');
        $minPhp = Options::requireString($input, 'min-php');
        $templateDir = Options::requireString($input, 'template-dir');
        $outputPath = Options::requireString($input, 'output');

        $generator = new InstallPagesGenerator($templateDir);

        $rendered = match ($mode) {
            'install' => $generator->renderInstall($version, $minPhp),
            'upgrade' => $generator->renderUpgrade(
                $version,
                Options::requireString($input, 'previous-version'),
                $minPhp,
            ),
            default => throw new \RuntimeException("--mode must be 'install' or 'upgrade'"),
        };

        return Outputs::writeOrEcho($output, $outputPath, $rendered);
    }
};

$app = new Application('gen-install-pages', '0.1.0');
$app->add($command);
$name = $command->getName();
if ($name === null) {
    throw new RuntimeException('Command name not set');
}
$app->setDefaultCommand($name, true);
$app->run();
