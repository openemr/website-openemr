<?php

/**
 * Render the per-release install or upgrade page from a Hugo template.
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

final class InstallPagesGenerator
{
    public const DEFAULT_MIN_PHP = '8.2';

    public function __construct(
        private readonly string $templateDir,
    ) {
    }

    public function renderInstall(string $version, string $minPhp = self::DEFAULT_MIN_PHP): string
    {
        return $this->renderTemplate('install.md.template', [
            '__VERSION__' => $version,
            '__MIN_PHP__' => $minPhp,
        ]);
    }

    public function renderUpgrade(
        string $version,
        string $previousVersion,
        string $minPhp = self::DEFAULT_MIN_PHP,
    ): string {
        return $this->renderTemplate('upgrade.md.template', [
            '__VERSION__' => $version,
            '__PREVIOUS_VERSION__' => $previousVersion,
            '__MIN_PHP__' => $minPhp,
        ]);
    }

    /**
     * @param array<string, string> $vars
     */
    private function renderTemplate(string $name, array $vars): string
    {
        $path = $this->templateDir . '/' . $name;
        $template = @file_get_contents($path);
        if ($template === false) {
            throw new RuntimeException("Template not readable: $path");
        }

        $rendered = strtr($template, $vars);
        if (str_contains($rendered, '__') && preg_match('/__[A-Z_]+__/', $rendered, $missing) === 1) {
            throw new RuntimeException("Unsubstituted placeholder in $name: " . $missing[0]);
        }

        return $rendered;
    }
}
