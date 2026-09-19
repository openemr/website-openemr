<?php

/**
 * Resolve GitHub usernames to their public display names.
 *
 * Used by AcknowledgementsGenerator to canonicalize Co-authored-by
 * trailers whose author string uses a GitHub username (e.g. "bradymiller")
 * paired with a GitHub noreply email
 * (`<id>+<username>@users.noreply.github.com`). Without canonicalization
 * those records would appear as a separate row from the same person's
 * primary-author commits (which typically use a personal email + real
 * name) — the acknowledgements page ends up with duplicate rows for
 * one contributor.
 *
 * Implementations must:
 *   - Deduplicate the input list; one lookup per unique username.
 *   - Return a map of username → display name (or username itself as
 *     fallback when GitHub has no `name` set for that user, or when
 *     any lookup fails).
 *   - Never throw. Network / rate-limit / missing-user errors fall
 *     back to the input username so the page still renders.
 *
 * @package   openemr/website-openemr
 * @link      https://www.open-emr.org
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace OpenEMR\ReleaseDocs;

interface GitHubUserResolver
{
    /**
     * @param list<string> $usernames
     * @return array<string, string> username => display name (or username on failure/absence)
     */
    public function resolveNames(array $usernames): array;
}
