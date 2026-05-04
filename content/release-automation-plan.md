---
draft: true
title: "Release automation — website-openemr slice"
---

# Release automation — `website-openemr` slice (the docs PR)

Tracks: openemr/openemr-devops#664 (refines #662, overlaps with #638)

This repo owns the **docs PR** — the consumer of release events from the
`openemr/openemr` conductor. The wiki's per-release documentation work moves
here, where it becomes diffable, version-pinned, and reviewable in pull
requests instead of edited live on a MediaWiki instance.

## Role in the flow

```
openemr/openemr release-prep PR  ── merge → tag v8_1_0
            │                              │
            └── (push to rel-*) ───────────┼──→ website-openemr docs PR  ← this repo
                                           │
                                           └──→ openemr-devops infra PR
```

Doesn't gate the tag — consumes it. Long-lived PR per `rel-*` release branch,
force-updated on every regeneration so history doesn't bloat.

## Why move docs off the wiki

For docs that are *release artifacts* (install/upgrade for v8.1.0, OpenAPI
spec, EHI/B10 schemaspy output, acknowledgements, release notes, ONC cert
page), publishing here gives us:

- PR review on every change
- Version-pinned content (Hugo `version` param)
- One-line redirects via Hugo `aliases` frontmatter (no MediaWiki redirect
  hacks)
- No need for a MediaWiki bot account or its credentials

The wiki keeps *living* community references — generic howtos, FAQ, anything
not tied to a specific release.

## Generated content

| Artifact                   | Source                                              | Destination                                |
| -------------------------- | --------------------------------------------------- | ------------------------------------------ |
| OpenAPI YAML               | `swagger/openemr-api.yaml` from openemr/openemr     | `static/api/openemr-<version>.yaml`        |
| EHI / B10 schemaspy HTML   | schemaspy run against an installed `rel-*`          | `website-openemr-files`: `files/openemr-<version>-ehi/` |
| Install / upgrade pages    | Hugo template + version param                       | `content/installation/<version>.md`        |
| Acknowledgements           | `git shortlog v8_0_0..v8_1_0` in openemr/openemr    | `content/about/acknowledgements/<version>.md` |
| Release-notes draft        | Milestone PRs grouped by `feat:` / `bug:` / `refactor:` / `chore:` prefix | `content/release-notes/<version>.md` |
| Hugo aliases               | Wiki URL → new page                                 | Frontmatter `aliases:` on each page        |
| ONC cert page              | Template + cert metadata                            | `content/certification/<version>.md`       |

The grouping convention for the release-notes draft matches the existing
`openemr-dev:create-release-change-log` CLI.

## Release-status shortcode

Every generated page renders with a release-status shortcode:

```
{{< release-status version="8.1.0" sha="<sha>" >}}
```

Shows `DRAFT — based on rel-810 @ <sha>` until the conductor's `openemr-tag`
event flips it to `FINAL — released YYYY-MM-DD`. The shortcode reads from a
small JSON manifest (`data/releases.json`) updated by the workflow.

## Components to build

In dependency order:

1. **Hugo templates and shortcodes.**
   - `layouts/shortcodes/release-status.html` — DRAFT/FINAL banner.
   - `layouts/_default/release-page.html` — install/upgrade base layout.
   - `data/releases.json` schema.

2. **Workflow `.github/workflows/release-docs.yml`.**
   - Trigger: `repository_dispatch` (`types: [openemr-rel-cut,
     openemr-rel-update, openemr-tag]`) plus `workflow_dispatch`.
   - Steps:
     - Check out openemr/openemr at the dispatched sha (sparse: `swagger/`,
       `version.php`, `acknowledge_license_cert.html`).
     - Run generation scripts.
     - Push large EHI tree to `website-openemr-files` (separate PR there).
     - If diff, force-push to `release-docs/<version>` and open/update a
       draft PR here.
     - On `openemr-tag`, flip `data/releases.json` status to FINAL and
       merge-ready.

3. **Generation scripts (`tools/release-docs/`, PHP + composer).**
   Mirrors the `tools/release/` pattern already established in
   `openemr-devops` (composer + `bin/*.php` + `src/` + `Taskfile.yml`).
   PHP keeps the language footprint aligned with the broader OpenEMR
   ecosystem.
   - `bin/gen-acknowledgements.php` — wraps `git shortlog`.
   - `bin/gen-release-notes.php` — fetches milestone PRs via the GitHub API,
     groups by `feat:` / `bug:` / `refactor:` / `chore:` prefix.
   - `bin/gen-install-pages.php` — renders Hugo content from templates +
     version params.
   - `bin/gen-aliases.php` — emits Hugo `aliases:` frontmatter from a
     wiki-URL → new-URL mapping table (one-time backfill, then maintained).
   - Where Hugo can do the work natively (e.g. `aliases:` already authored in
     frontmatter, shortcodes for the release-status banner), prefer Hugo
     over generation. Reach for PHP only when templating outside Hugo's
     reach (shortlog/API queries, mapping-table expansion).

4. **One-time backfill.**
   - Create Hugo pages for the current shipped version so the redirect chain
     has a target. Without this, every alias 404s.
   - Mapping table for legacy URLs:
     `OpenEMR 8 API` → `/api/8.1.0/`,
     `OpenEMR Installation` → `/installation/8.1.0/`, etc.

5. **`website-openemr-files` companion workflow.**
   - Receives a sub-dispatch from this repo when EHI/B10 binaries are ready.
   - Opens its own draft PR adding `files/openemr-<version>-ehi/`.
   - Separate repo because the binaries are too large for the Hugo repo.

## Permissions self-check

`.github/workflows/release-permissions-check.yml` (manual `workflow_dispatch`).
Mints an App token from `RELEASE_APP_ID` + `RELEASE_APP_PRIVATE_KEY` and
probes what this repo's docs workflow performs:

- `GET /installation/repositories` — confirm this repo is in the install list.
- Create + delete a throwaway branch `release-permissions-check/<run-id>` —
  confirm `contents:write`.
- Open + close a draft PR from that branch — confirm `pull-requests:write`.
- Send a no-op `repository_dispatch` (event type `release-permissions-probe`)
  to **openemr/website-openemr-files** — confirm cross-repo `actions:write`
  on the files repo.

Fails loudly with the missing permission name. Run after installing the App
on this repo and the files repo; re-run if secrets are rotated.

## Out of scope here

- The mechanical version bumps in openemr/openemr — conductor PR.
- The CI matrix rotation — openemr-devops PR.
- Wiki cleanup / archival — separate, manual, post-cutover.

## Resolved decisions

- **Long-lived docs PR per release**, not rebuild-fresh-per-dispatch. The
  release manager gets one URL to watch from `rel-*` cut through `openemr-tag`.
- **Pre-release docs are published, not hidden** — same `/installation/8.1.0/`
  URL throughout the cycle. The release-status shortcode renders a DRAFT
  banner until the `openemr-tag` event flips it to FINAL.
- **Released outputs are kept forever; pre-release outputs are mutable.**
  EHI / B10 schemaspy artifacts for any version that ever shipped stay
  accessible indefinitely (historical reproducibility). Pre-release artifacts
  for a `rel-*` branch can be replaced or removed as long as no `openemr-tag`
  event has fired for that version.

## Hypotheses (claims this slice rises or falls on)

1. **Wiki release content is generated, not authored.** Moving install /
   upgrade / acknowledgements / release-notes / ONC pages to Hugo loses
   nothing substantive. What stays on the wiki is genuinely "living."
2. **Hugo aliases fully replace MediaWiki redirect semantics** for
   SEO-indexed inbound links — old URLs survive the migration.
3. **Schemaspy and OpenAPI generation are headless / DB-light enough for CI**
   (or can be made so by a small installer script).
4. **`website-openemr-files` is the right home for large EHI/B10 binaries**
   and accepts `repository_dispatch` from this workflow.
5. **A long-lived docs PR per release is the right UX** for the release
   manager — one URL to watch — versus rebuilding fresh per dispatch.
6. **Force-pushing the docs PR is acceptable to reviewers** even though it
   drops inline comments on regenerated content.

## Assumptions

- An app or PAT with `contents:write` + `pull-requests:write` on this repo
  and `website-openemr-files` will be provisioned.
- The `releases.json` data file is an acceptable source of truth for the
  DRAFT/FINAL banner state.
- Maintainers accept losing per-release edit history on the wiki.
- The wiki-URL → new-URL mapping table can be authored once and maintained
  by hand; no need to scrape MediaWiki.
- Prerelease docs are OK to publish (decision still open: `-rc` suffix vs.
  hidden until FINAL).

## Testing

### Independent / per-component (fast, no cross-repo)

- **Generation-script unit tests** (PHPUnit). `gen-acknowledgements`,
  `gen-release-notes`, `gen-install-pages`, `gen-aliases` each get a test
  against frozen fixtures (`git shortlog` output, GitHub API JSON, template
  inputs). Snapshot the rendered output; assert deterministic.
- **Release-status shortcode test.** Hugo template test: feed a small
  content fixture and a stub `releases.json`, assert the rendered HTML
  matches DRAFT and FINAL variants.
- **Aliases lint.** Validate every entry in the wiki-URL mapping table
  resolves to an existing Hugo page; fail CI on a dangling redirect.
- **`releases.json` schema.** JSON schema for the manifest; validate on
  every change.
- **Dispatch-payload schema.** Same schema file shared with conductor and
  devops repos; validate inbound payloads.

### Single-repo integration

- **`workflow_dispatch` synthetic run.** Fire the docs workflow with a
  hand-crafted payload, assert the docs PR opens / updates with the expected
  generated pages and `releases.json` change.
- **Re-dispatch idempotence.** Fire the same payload twice, assert the
  resulting PR is byte-identical.
- **Hugo build smoke.** After regeneration, run `hugo --gc --minify` and
  fail on any broken-link / template warnings.

### E2E (cross-repo, only meaningful in a fork triplet)

- **Full dry-run.** Cut `rel-test` in a fork of openemr/openemr → conductor
  dispatch lands here → confirm the docs PR opens with generated pages →
  tag in the fork → confirm DRAFT flips to FINAL in `releases.json` and
  the rendered banner.
- **Binaries handoff.** Confirm the sub-dispatch to `website-openemr-files`
  fires and its companion PR opens with the EHI tree.
- **Race rehearsal.** Tag while a docs regeneration is mid-run, confirm
  the FINAL flip lands cleanly without overwriting in-flight content.

## Status

Draft plan. Depends on the conductor workflow in `openemr/openemr` being far
enough along to emit `repository_dispatch` events; until then this workflow
runs on `workflow_dispatch` only.
