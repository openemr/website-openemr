#!/usr/bin/env php
<?php

/**
 * Emit the `version=` / `tag=` / `branch=` / `forum_url=` lines the
 * release-announcements workflow appends to $GITHUB_OUTPUT.
 *
 * Two input modes:
 *
 *   --head-ref <ref>
 *       Parse the head ref of a merged pull_request event and derive
 *       version + tag + branch canonically. The workflow supplies
 *       `github.event.pull_request.head.ref`, which for the release-docs
 *       PRs is `release-docs/<version>` (e.g., `release-docs/8.2.0`).
 *       Version is stripped from the ref; tag is `v<major>_<minor>_<patch>`;
 *       branch is `rel-<major><minor>0`.
 *
 *   --release-version=<v> --release-tag=<t> --release-branch=<b>
 *       Manual workflow_dispatch fallback for maintainer re-renders when
 *       the pull_request:closed trigger missed or the drafts artifact
 *       was lost. All three flags required together in this mode.
 *
 * The two modes are mutually exclusive. Validation patterns mirror the
 * canonical shapes from openemr-devops's dispatch.schema.json (version,
 * tag, branch); a malformed value aborts the step instead of producing
 * artifacts that reference "null".
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
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\SingleCommandApplication;

const VERSION_PATTERN = '/^\d+\.\d+\.\d+$/';
const TAG_PATTERN = '/^v\d+_\d+_\d+$/';
const BRANCH_PATTERN = '/^rel-[0-9]+$/';
const HEAD_REF_PREFIX = 'release-docs/';

(new SingleCommandApplication())
    ->setName('derive-announcement-inputs')
    ->setDescription('Emit version/tag/branch/forum_url lines for the announcements workflow')
    ->addOption(
        'head-ref',
        null,
        InputOption::VALUE_REQUIRED,
        "Pull-request head ref of the form `release-docs/<version>`. Mutually exclusive with --release-* flags.",
    )
    ->addOption('release-version', null, InputOption::VALUE_REQUIRED, 'Release version (e.g. 8.1.0)')
    ->addOption('release-tag', null, InputOption::VALUE_REQUIRED, 'Annotated release tag (e.g. v8_1_0)')
    ->addOption('release-branch', null, InputOption::VALUE_REQUIRED, 'Release branch (e.g. rel-810)')
    ->addOption(
        'forum-url',
        null,
        InputOption::VALUE_REQUIRED,
        'Per-release Discourse thread URL; empty value falls back to the placeholder downstream',
        '',
    )
    ->setCode(function (InputInterface $input, OutputInterface $output): int {
        // Stdout is reserved for the GITHUB_OUTPUT key=value lines the
        // workflow appends with `>>`. Errors must not pollute it.
        $err = $output instanceof ConsoleOutputInterface ? $output->getErrorOutput() : $output;
        $str = static function (string $name) use ($input): string {
            $value = $input->getOption($name);
            return is_string($value) ? $value : '';
        };
        $headRef = $str('head-ref');
        $version = $str('release-version');
        $tag = $str('release-tag');
        $branch = $str('release-branch');

        $flagsProvided = array_filter(
            [$version, $tag, $branch],
            static fn(string $v): bool => $v !== '',
        );
        if ($headRef !== '' && $flagsProvided !== []) {
            $err->writeln('<error>--head-ref is mutually exclusive with --release-* flags</error>');
            return 1;
        }
        if ($headRef === '' && count($flagsProvided) !== 3) {
            $err->writeln(
                '<error>Provide either --head-ref or all of'
                . ' --release-version/--release-tag/--release-branch</error>',
            );
            return 1;
        }

        if ($headRef !== '') {
            if (!str_starts_with($headRef, HEAD_REF_PREFIX)) {
                $err->writeln(sprintf(
                    "<error>--head-ref must start with '%s' (got: %s)</error>",
                    HEAD_REF_PREFIX,
                    $headRef,
                ));
                return 1;
            }
            $version = substr($headRef, strlen(HEAD_REF_PREFIX));
            if (preg_match(VERSION_PATTERN, $version) !== 1) {
                $err->writeln(sprintf(
                    "<error>version parsed from --head-ref does not match %s (got: %s)</error>",
                    VERSION_PATTERN,
                    $version,
                ));
                return 1;
            }
            [$major, $minor] = explode('.', $version, 3);
            $tag = 'v' . str_replace('.', '_', $version);
            $branch = "rel-{$major}{$minor}0";
        } else {
            $fields = [
                ['version', $version, VERSION_PATTERN],
                ['tag', $tag, TAG_PATTERN],
                ['branch', $branch, BRANCH_PATTERN],
            ];
            foreach ($fields as [$name, $value, $pattern]) {
                if (preg_match($pattern, $value) !== 1) {
                    $err->writeln(sprintf(
                        "<error>field %s does not match expected shape %s (got: %s)</error>",
                        $name,
                        $pattern,
                        $value,
                    ));
                    return 1;
                }
            }
            // Even when each field is individually shape-valid, the trio
            // could still cross-refer (--release-version=8.1.0 with
            // --release-tag=v9_9_9): shape passes but the tag doesn't
            // describe the version. Derive canonical tag+branch from the
            // version and require the caller-supplied values to match, so
            // a workflow_dispatch typo can't silently ship announcement
            // drafts with mismatched links.
            [$major, $minor] = explode('.', $version, 3);
            $expectedTag = 'v' . str_replace('.', '_', $version);
            $expectedBranch = "rel-{$major}{$minor}0";
            if ($tag !== $expectedTag || $branch !== $expectedBranch) {
                $err->writeln(sprintf(
                    "<error>tag/branch do not match version: expected tag=%s branch=%s"
                    . " for version %s, got tag=%s branch=%s</error>",
                    $expectedTag,
                    $expectedBranch,
                    $version,
                    $tag,
                    $branch,
                ));
                return 1;
            }
        }

        // forum_url is user-supplied and emitted verbatim to stdout, which
        // the workflow appends to $GITHUB_OUTPUT. A value containing CR/LF
        // would open additional key=value lines and let a caller inject
        // extra workflow outputs. URLs never contain literal control chars
        // (RFC 3986 requires percent-encoding), so rejecting is safe.
        $forumUrl = $str('forum-url');
        if (str_contains($forumUrl, "\r") || str_contains($forumUrl, "\n")) {
            $err->writeln('<error>--forum-url must be a single-line value (no CR/LF)</error>');
            return 1;
        }

        // Emit forum_url verbatim (possibly empty); downstream renderers
        // substitute their own placeholder when the maintainer hasn't
        // supplied a real URL. Keeping the placeholder string out of the
        // pipeline avoids Taskfile/Go-template confusion over the literal
        // braces.
        $output->writeln(sprintf('version=%s', $version));
        $output->writeln(sprintf('tag=%s', $tag));
        $output->writeln(sprintf('branch=%s', $branch));
        $output->writeln(sprintf('forum_url=%s', $forumUrl));
        return 0;
    })
    ->run();
