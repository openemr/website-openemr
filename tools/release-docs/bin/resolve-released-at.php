#!/usr/bin/env php
<?php

/**
 * Emit `released_at=<date>` for $GITHUB_OUTPUT, picking the first
 * non-empty of four candidate sources in priority order:
 *
 *   1. --input-released-at        explicit workflow_dispatch override
 *   2. --release-published-at     GitHub Release object publishedAt
 *   3. --tagger-date              annotated tag object's tagger.date
 *   4. --dispatched-at            dispatch envelope's dispatched_at
 *
 * The workflow gathers each source via inline gh/jq calls, then hands
 * the four candidates here to pick the winner. Extraction keeps the
 * "which value wins" logic in a testable CLI (see
 * ResolveReleasedAtCliTest) rather than buried in workflow shell.
 *
 * On success emits `released_at=<date>\n` to stdout. When all four
 * candidates are empty, emits nothing (stdout stays clean, so the
 * workflow's redirect to $GITHUB_OUTPUT doesn't corrupt the output
 * file) and returns 0; the downstream update-manifest.php step's
 * fallback + stderr warning surfaces the missing-source case.
 *
 * See openemr/website-openemr#145.
 *
 * @package   openemr/website-openemr
 * @link      https://www.open-emr.org
 * @author    Michael A. Smith <michael@opencoreemr.com>
 * @copyright Copyright (c) 2026 OpenCoreEMR Inc.
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\SingleCommandApplication;

(new SingleCommandApplication())
    ->setName('resolve-released-at')
    ->setDescription('Pick the first non-empty released_at candidate for the manifest write')
    ->addOption(
        'input-released-at',
        null,
        InputOption::VALUE_REQUIRED,
        'Explicit workflow_dispatch override',
        '',
    )
    ->addOption(
        'release-published-at',
        null,
        InputOption::VALUE_REQUIRED,
        'GitHub Release object publishedAt (YYYY-MM-DD)',
        '',
    )
    ->addOption(
        'tagger-date',
        null,
        InputOption::VALUE_REQUIRED,
        'Annotated tag object tagger.date (YYYY-MM-DD)',
        '',
    )
    ->addOption(
        'dispatched-at',
        null,
        InputOption::VALUE_REQUIRED,
        'Dispatch envelope dispatched_at (YYYY-MM-DD)',
        '',
    )
    ->setCode(function (InputInterface $input, OutputInterface $output): int {
        $str = static function (string $name) use ($input): string {
            $value = $input->getOption($name);
            return is_string($value) ? trim($value) : '';
        };
        $candidates = ['input-released-at', 'release-published-at', 'tagger-date', 'dispatched-at'];
    foreach ($candidates as $option) {
        $value = $str($option);
        if ($value !== '') {
            $output->writeln(sprintf('released_at=%s', $value));
            return 0;
        }
    }
        // Emit nothing when all sources are empty; downstream fallback
        // in update-manifest.php (and its stderr warning) handles it.
        return 0;
    })
    ->run();
