<?php

/**
 * Regenerate the EHI / ONC (b)(10) SchemaSpy schema documentation.
 *
 * Reads the in-scope table set from openemr's `b10-tables.yml`, builds the
 * SchemaSpy include filter, and runs the bundled schemaspy.jar against a
 * freshly-loaded schema. The table set is read from the openemr checkout so it
 * always matches the schema of the release being documented.
 *
 * @package   openemr/website-openemr
 * @link      https://www.open-emr.org
 * @author    Michael A. Smith <michael@opencoreemr.com>
 * @copyright Copyright (c) 2026 OpenCoreEMR Inc.
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace OpenEMR\ReleaseDocs;

use RuntimeException;
use Symfony\Component\Process\Process;
use Symfony\Component\Yaml\Yaml;

final class EhiSchemaGenerator
{
    private const string DB_TYPE = 'mariadb';

    public function generate(EhiSchemaSpyConfig $config): void
    {
        $tables = $this->parseTables($this->readFile($config->tablesFile));
        $command = $this->buildCommand($config, $this->includeRegex($tables));

        $process = new Process($command);
        $process->setTimeout(null);
        $process->mustRun();

        $indexHtml = $config->outputDir . '/index.html';
        if (!is_file($indexHtml)) {
            throw new RuntimeException("SchemaSpy did not produce $indexHtml");
        }
    }

    /**
     * Flatten the grouped (b)(10) table set into a sorted, de-duplicated list.
     *
     * @return list<non-empty-string>
     */
    public function parseTables(string $yamlContent): array
    {
        $parsed = Yaml::parse($yamlContent);
        if (!is_array($parsed) || !isset($parsed['groups']) || !is_array($parsed['groups'])) {
            throw new RuntimeException('b10-tables.yml must contain a top-level "groups" mapping');
        }

        $tables = [];
        foreach ($parsed['groups'] as $members) {
            if (!is_array($members)) {
                throw new RuntimeException('Each group in b10-tables.yml must be a list of table names');
            }
            foreach ($members as $table) {
                if (!is_string($table) || $table === '') {
                    throw new RuntimeException('Table names in b10-tables.yml must be non-empty strings');
                }
                $tables[] = $table;
            }
        }

        $unique = array_values(array_unique($tables));
        sort($unique);

        return $unique;
    }

    /**
     * SchemaSpy include filter — a regex alternation of the in-scope tables.
     *
     * @param list<non-empty-string> $tables
     */
    public function includeRegex(array $tables): string
    {
        if ($tables === []) {
            throw new RuntimeException('Cannot build an include filter from an empty table list');
        }

        return '(' . implode('|', $tables) . ')';
    }

    /**
     * Assemble the SchemaSpy java argv. Pure (no IO), so it is unit-testable.
     *
     * @return list<string>
     */
    public function buildCommand(EhiSchemaSpyConfig $config, string $includeRegex): array
    {
        return [
            'java',
            '-jar', $config->jar,
            '-t', self::DB_TYPE,
            '-host', $config->dbHost,
            '-port', (string) $config->dbPort,
            '-db', $config->dbName,
            '-u', $config->dbUser,
            '-p', $config->dbPassword,
            '-s', $config->dbSchema,
            '-dp', $config->connectorJar,
            '-o', $config->outputDir,
            '-i', $includeRegex,
            '-meta', $config->metaPath,
            '-template', $config->templateDir,
            '-vizjs',
            '-norows',
            '-noimplied',
            '-nopages',
        ];
    }

    private function readFile(string $path): string
    {
        $contents = file_get_contents($path);
        if ($contents === false) {
            throw new RuntimeException("Could not read $path");
        }

        return $contents;
    }
}
