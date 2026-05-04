#!/usr/bin/env php
<?php

/**
 * Validate a dispatch payload against contracts/dispatch.schema.json.
 *
 * Reads JSON from a file or stdin, runs the canonical schema, and prints
 * any validation errors. Used by the release-docs workflow before any
 * generators run.
 *
 * @package   openemr/website-openemr
 * @link      https://www.open-emr.org
 * @author    Michael A. Smith <michael@opencoreemr.com>
 * @copyright Copyright (c) 2026 OpenCoreEMR Inc.
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

use OpenEMR\ReleaseDocs\Cli\Options;
use OpenEMR\ReleaseDocs\Dispatch\DispatchValidator;
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
            ->setName('validate-dispatch')
            ->setDescription('Validate a dispatch payload against contracts/dispatch.schema.json.')
            ->addOption('payload', null, InputOption::VALUE_REQUIRED, 'Path to JSON payload file (- for stdin)')
            ->addOption(
                'schema',
                null,
                InputOption::VALUE_REQUIRED,
                'Path to dispatch schema',
                __DIR__ . '/../contracts/dispatch.schema.json',
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $payloadOpt = Options::requireString($input, 'payload');
        $schemaPath = Options::requireString($input, 'schema');

        $payloadJson = $payloadOpt === '-'
            ? (string) file_get_contents('php://stdin')
            : (string) @file_get_contents($payloadOpt);
        if ($payloadJson === '') {
            $output->writeln('<error>payload is empty or unreadable</error>');
            return Command::FAILURE;
        }

        $errors = (new DispatchValidator($schemaPath))->validate($payloadJson);
        if ($errors === []) {
            $output->writeln('OK');
            return Command::SUCCESS;
        }

        foreach ($errors as $err) {
            $output->writeln("<error>$err</error>");
        }

        return Command::FAILURE;
    }
};

$app = new Application('validate-dispatch', '0.1.0');
$app->add($command);
$name = $command->getName();
if ($name === null) {
    throw new RuntimeException('Command name not set');
}
$app->setDefaultCommand($name, true);
$app->run();
