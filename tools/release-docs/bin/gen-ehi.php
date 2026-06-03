#!/usr/bin/env php
<?php

/**
 * Regenerate the EHI / ONC (b)(10) SchemaSpy documentation for a release.
 *
 * Loads the (b)(10) table set and bundled SchemaSpy assets from an
 * openemr/openemr checkout, then runs schemaspy.jar against an already-loaded
 * schema to produce a browsable HTML tree under --output.
 *
 * @package   openemr/website-openemr
 * @link      https://www.open-emr.org
 * @author    Michael A. Smith <michael@opencoreemr.com>
 * @copyright Copyright (c) 2026 OpenCoreEMR Inc.
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

use OpenEMR\ReleaseDocs\Cli\Options;
use OpenEMR\ReleaseDocs\EhiSchemaGenerator;
use OpenEMR\ReleaseDocs\EhiSchemaSpyConfig;
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
            ->setName('gen-ehi')
            ->setDescription('Regenerate the EHI (b)(10) SchemaSpy documentation for a release.')
            ->addOption('openemr-checkout', null, InputOption::VALUE_REQUIRED, 'Path to an openemr/openemr checkout')
            ->addOption('release-version', null, InputOption::VALUE_REQUIRED, 'Release version (e.g. 8.1.0)')
            ->addOption('output', null, InputOption::VALUE_REQUIRED, 'Directory to write the SchemaSpy HTML tree')
            ->addOption('db-host', null, InputOption::VALUE_REQUIRED, 'Database host', '127.0.0.1')
            ->addOption('db-port', null, InputOption::VALUE_REQUIRED, 'Database port', '3306')
            ->addOption('db-name', null, InputOption::VALUE_REQUIRED, 'Database name', 'openemr')
            ->addOption('db-user', null, InputOption::VALUE_REQUIRED, 'Database user', 'openemr')
            ->addOption('db-password', null, InputOption::VALUE_REQUIRED, 'Database password', 'openemr')
            ->addOption('db-schema', null, InputOption::VALUE_REQUIRED, 'Database schema', 'openemr')
            ->addOption('tables-file', null, InputOption::VALUE_REQUIRED, 'Override path to b10-tables.yml')
            ->addOption('schemaspy-dir', null, InputOption::VALUE_REQUIRED, 'Override path to the schemaspy asset dir');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $config = EhiSchemaSpyConfig::fromOpenemrCheckout(
            openemrCheckout: Options::requireString($input, 'openemr-checkout'),
            outputDir: Options::requireString($input, 'output'),
            dbHost: Options::requireString($input, 'db-host'),
            dbPort: (int) Options::requireString($input, 'db-port'),
            dbName: Options::requireString($input, 'db-name'),
            dbUser: Options::requireString($input, 'db-user'),
            dbPassword: Options::requireString($input, 'db-password'),
            dbSchema: Options::requireString($input, 'db-schema'),
            tablesFile: Options::optionalString($input, 'tables-file'),
            schemaspyDir: Options::optionalString($input, 'schemaspy-dir'),
        );

        (new EhiSchemaGenerator())->generate($config);

        $output->writeln("Wrote {$config->outputDir}/index.html");
        return Command::SUCCESS;
    }
};

$app = new Application('gen-ehi', '0.1.0');
$app->add($command);
$name = $command->getName();
if ($name === null) {
    throw new RuntimeException('Command name not set');
}
$app->setDefaultCommand($name, true);
$app->run();
