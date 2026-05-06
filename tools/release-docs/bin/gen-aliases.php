#!/usr/bin/env php
<?php

/**
 * Apply or lint Hugo aliases from a wiki-URL → page mapping.
 *
 * In `--check` mode verifies every mapping target points to an existing
 * Hugo page; otherwise merges the aliases into each page's frontmatter.
 *
 * @package   openemr/website-openemr
 * @link      https://www.open-emr.org
 * @author    Michael A. Smith <michael@opencoreemr.com>
 * @copyright Copyright (c) 2026 OpenCoreEMR Inc.
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

use OpenEMR\ReleaseDocs\AliasesGenerator;
use OpenEMR\ReleaseDocs\Cli\Options;
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
            ->setName('gen-aliases')
            ->setDescription('Apply or lint Hugo aliases from a wiki-URL → page mapping.')
            ->addOption('mapping', null, InputOption::VALUE_REQUIRED, 'Path to aliases YAML mapping')
            ->addOption('content-root', null, InputOption::VALUE_REQUIRED, 'Hugo content/ directory')
            ->addOption('check', null, InputOption::VALUE_NONE, 'Lint only; do not write files');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $mappingPath = Options::requireString($input, 'mapping');
        $contentRoot = Options::requireString($input, 'content-root');

        $generator = new AliasesGenerator($contentRoot);
        $mapping = $generator->loadMapping($mappingPath);

        $errors = $generator->lint($mapping);
        if ($errors !== []) {
            foreach ($errors as $error) {
                $output->writeln("<error>$error</error>");
            }
            return Command::FAILURE;
        }

        if ($input->getOption('check') === true) {
            $output->writeln('OK: ' . count($mapping) . ' targets resolve.');
            return Command::SUCCESS;
        }

        $written = $generator->apply($mapping);
        if ($written === []) {
            $output->writeln('No files needed updating.');
            return Command::SUCCESS;
        }
        foreach ($written as $path) {
            $output->writeln("Updated $path");
        }

        return Command::SUCCESS;
    }
};

$app = new Application('gen-aliases', '0.1.0');
$app->add($command);
$name = $command->getName();
if ($name === null) {
    throw new RuntimeException('Command name not set');
}
$app->setDefaultCommand($name, true);
$app->run();
