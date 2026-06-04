<?php

/**
 * Resolved inputs for one EHI / ONC (b)(10) SchemaSpy generation run.
 *
 * @package   openemr/website-openemr
 * @link      https://www.open-emr.org
 * @author    Michael A. Smith <michael@opencoreemr.com>
 * @copyright Copyright (c) 2026 OpenCoreEMR Inc.
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace OpenEMR\ReleaseDocs;

final readonly class EhiSchemaSpyConfig
{
    public function __construct(
        public string $tablesFile,
        public string $jar,
        public string $connectorJar,
        public string $metaPath,
        public string $templateDir,
        public string $outputDir,
        public string $dbHost,
        public int $dbPort,
        public string $dbName,
        public string $dbUser,
        public string $dbPassword,
        public string $dbSchema,
    ) {
    }

    /**
     * Derive the SchemaSpy asset paths from an openemr/openemr checkout.
     *
     * The (b)(10) table set and the bundled schemaspy.jar, connector, schema
     * metadata, and mustache template all live in the openemr repo, versioned
     * with the schema. `tablesFile` / `schemaspyDir` overrides exist for the
     * retroactive path, where assets come from openemr master while the schema
     * itself is loaded from an older tagged release.
     */
    public static function fromOpenemrCheckout(
        string $openemrCheckout,
        string $outputDir,
        string $dbHost,
        int $dbPort,
        string $dbName,
        string $dbUser,
        string $dbPassword,
        string $dbSchema,
        ?string $tablesFile = null,
        ?string $schemaspyDir = null,
    ): self {
        $checkout = rtrim($openemrCheckout, '/');
        $schemaspy = $schemaspyDir ?? "$checkout/Documentation/EHI_Export/schemaspy";
        $jars = "$schemaspy/jars";

        return new self(
            tablesFile: $tablesFile ?? "$checkout/Documentation/EHI_Export/b10-tables.yml",
            jar: "$jars/schemaspy.jar",
            connectorJar: "$jars/mariadb-java-client-3.4.1.jar",
            metaPath: "$schemaspy/schemas/",
            templateDir: "$schemaspy/layout/",
            outputDir: rtrim($outputDir, '/'),
            dbHost: $dbHost,
            dbPort: $dbPort,
            dbName: $dbName,
            dbUser: $dbUser,
            dbPassword: $dbPassword,
            dbSchema: $dbSchema,
        );
    }
}
