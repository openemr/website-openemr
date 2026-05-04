#!/usr/bin/env php
<?php

/**
 * Apply a dispatch payload to data/releases.json.
 *
 * Validates the payload against the dispatch schema, then projects it onto
 * the release-status manifest the Hugo shortcode reads.
 *
 * @package   openemr/website-openemr
 * @link      https://www.open-emr.org
 * @author    Michael A. Smith <michael@opencoreemr.com>
 * @copyright Copyright (c) 2026 OpenCoreEMR Inc.
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

use OpenEMR\ReleaseDocs\Cli\Options;
use OpenEMR\ReleaseDocs\Manifest\DispatchEvent;
use OpenEMR\ReleaseDocs\Manifest\ReleasesManifest;
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
            ->setName('update-manifest')
            ->setDescription('Apply a dispatch payload to data/releases.json.')
            ->addOption('payload', null, InputOption::VALUE_REQUIRED, 'Path to JSON payload file (- for stdin)')
            ->addOption('manifest', null, InputOption::VALUE_REQUIRED, 'Path to releases.json')
            ->addOption('schema', null, InputOption::VALUE_REQUIRED, 'Path to releases.schema.json')
            ->addOption(
                'released-at',
                null,
                InputOption::VALUE_REQUIRED,
                'ISO date for FINAL flip (defaults to today UTC for openemr-tag)',
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $payloadOpt = Options::requireString($input, 'payload');
        $manifestPath = Options::requireString($input, 'manifest');
        $schemaPath = Options::requireString($input, 'schema');

        $payloadJson = $payloadOpt === '-'
            ? (string) file_get_contents('php://stdin')
            : (string) @file_get_contents($payloadOpt);
        if ($payloadJson === '') {
            $output->writeln('<error>payload is empty or unreadable</error>');
            return Command::FAILURE;
        }

        $event = DispatchEvent::fromJson($payloadJson, $this->resolveReleasedAt($input, $payloadJson));
        (new ReleasesManifest($manifestPath, $schemaPath))->apply($event);

        $output->writeln(sprintf('Updated %s for %s (%s)', $manifestPath, $event->version, $event->event));
        return Command::SUCCESS;
    }

    private function resolveReleasedAt(InputInterface $input, string $payloadJson): ?string
    {
        $opt = $input->getOption('released-at');
        if (is_string($opt) && $opt !== '') {
            return $opt;
        }

        $payload = json_decode($payloadJson, true, 512, JSON_THROW_ON_ERROR);
        $event = is_array($payload) ? ($payload['event'] ?? null) : null;
        if ($event !== 'openemr-tag') {
            return null;
        }

        return gmdate('Y-m-d');
    }
};

$app = new Application('update-manifest', '0.1.0');
$app->add($command);
$name = $command->getName();
if ($name === null) {
    throw new RuntimeException('Command name not set');
}
$app->setDefaultCommand($name, true);
$app->run();
