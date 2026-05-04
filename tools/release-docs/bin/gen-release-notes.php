#!/usr/bin/env php
<?php

/**
 * Render the Hugo release-notes page for an OpenEMR release.
 *
 * Pulls merged PRs from the GitHub Search API in the given window and
 * groups them by Conventional-Commits prefix.
 *
 * @package   openemr/website-openemr
 * @link      https://www.open-emr.org
 * @author    Michael A. Smith <michael@opencoreemr.com>
 * @copyright Copyright (c) 2026 OpenCoreEMR Inc.
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

use GuzzleHttp\Client;
use OpenEMR\ReleaseDocs\Cli\Options;
use OpenEMR\ReleaseDocs\Cli\Outputs;
use OpenEMR\ReleaseDocs\ReleaseNotesGenerator;
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
            ->setName('gen-release-notes')
            ->setDescription('Render the Hugo release-notes page for an OpenEMR release.')
            ->addOption('owner', null, InputOption::VALUE_REQUIRED, 'GitHub owner', 'openemr')
            ->addOption('repo', null, InputOption::VALUE_REQUIRED, 'GitHub repo', 'openemr')
            ->addOption(
                'repo-path',
                null,
                InputOption::VALUE_REQUIRED,
                'Path to an openemr/openemr checkout (anchors the merged_at window on the previous tag)',
            )
            ->addOption(
                'prev-version',
                null,
                InputOption::VALUE_REQUIRED,
                'Previous release version (e.g. 8.0.0); used to compute the date window',
            )
            ->addOption('release-version', null, InputOption::VALUE_REQUIRED, 'Display version string')
            ->addOption('token', null, InputOption::VALUE_REQUIRED, 'GitHub token (default env GITHUB_TOKEN)')
            ->addOption('output', null, InputOption::VALUE_REQUIRED, 'Output path (- for stdout)', '-');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $owner = Options::requireString($input, 'owner');
        $repo = Options::requireString($input, 'repo');
        $repoPath = Options::requireString($input, 'repo-path');
        $prevVersion = Options::requireString($input, 'prev-version');
        $version = Options::requireString($input, 'release-version');
        $outputPath = Options::requireString($input, 'output');
        $token = self::resolveToken($input);

        $window = ReleaseNotesGenerator::dateWindow($repoPath, $prevVersion);

        $client = new Client([
            'base_uri' => 'https://api.github.com',
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Accept' => 'application/vnd.github+json',
                'User-Agent' => 'openemr-release-docs',
            ],
            'timeout' => 30.0,
        ]);

        $rendered = (new ReleaseNotesGenerator($client))
            ->generate($owner, $repo, $window['from'], $window['to'], $version);

        return Outputs::writeOrEcho($output, $outputPath, $rendered);
    }

    private static function resolveToken(InputInterface $input): string
    {
        $opt = $input->getOption('token');
        if (is_string($opt) && $opt !== '') {
            return $opt;
        }
        $env = getenv('GITHUB_TOKEN');
        if (is_string($env) && $env !== '') {
            return $env;
        }
        throw new \RuntimeException('GitHub token required (--token or GITHUB_TOKEN env)');
    }
};

$app = new Application('gen-release-notes', '0.1.0');
$app->add($command);
$name = $command->getName();
if ($name === null) {
    throw new RuntimeException('Command name not set');
}
$app->setDefaultCommand($name, true);
$app->run();
