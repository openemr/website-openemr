#!/usr/bin/env bash
# Regenerate SHA-256 and SHA-512 sidecar checksum files for every
# historical (SourceForge-era) OpenEMR release under static/checksums/.
#
# For each version that already has a .sha1 sidecar:
#   1. Download the tarball + zip from SourceForge into a temp dir.
#   2. Verify the downloaded artifact matches the committed .sha1.
#   3. Compute .sha256 + .sha512 alongside.
#   4. Write the new sidecar files into static/checksums/<version>/.
#
# The .sha1 verification catches SourceForge serving a mirror that
# diverged from the ship-time artifact (or a transport-level
# corruption). If verification fails, we abort loudly rather than
# committing an incorrect hash.
#
# One-shot: this script exists to bootstrap the initial set of
# .sha256/.sha512 files. Once committed, future releases publish these
# sidecars automatically as GitHub Release assets and don't need this
# script. Kept in the repo as an audit trail for how the historical
# hashes were derived + as a rerun path if a future maintainer needs
# to re-verify against SourceForge.
#
# Usage: scripts/gen-historical-checksums.sh [<version>...]
#   Empty args -> process every version under static/checksums/ that
#                 has a .sha1 sidecar but no .sha256 sidecar.
#   Named args -> process only the listed versions.
#
# See openemr/website-openemr#123.

set -euo pipefail

REPO_ROOT="$(cd "$(dirname "$0")/.." && pwd)"
CHECKSUMS_DIR="$REPO_ROOT/static/checksums"

# SourceForge URL templates (URL-encoded space in "OpenEMR Current" path).
sf_url() {
    local version="$1"
    local archive="$2"  # openemr-<v>.tar.gz or openemr-<v>.zip
    printf 'https://sourceforge.net/projects/openemr/files/OpenEMR%%20Current/%s/%s/download' \
        "$version" "$archive"
}

# Verify a downloaded file matches the committed .sha1 sidecar; die if not.
verify_sha1() {
    local file="$1"
    local expected_sha1_file="$2"
    if [ ! -f "$expected_sha1_file" ]; then
        echo "ERROR: expected .sha1 sidecar missing: $expected_sha1_file" >&2
        exit 1
    fi
    local expected="$(awk '{print $1}' "$expected_sha1_file")"
    local actual="$(sha1sum "$file" | awk '{print $1}')"
    if [ "$expected" != "$actual" ]; then
        echo "ERROR: SHA-1 mismatch for $file" >&2
        echo "  expected (from committed sidecar): $expected" >&2
        echo "  actual   (from SourceForge fetch): $actual" >&2
        exit 1
    fi
}

# Write .sha256 + .sha512 for a given archive.
write_sidecars() {
    local file="$1"
    local basename="$2"       # openemr-<v>.tar.gz or openemr-<v>.zip
    local target_dir="$3"
    local sha256 sha512
    sha256="$(sha256sum "$file" | awk '{print $1}')"
    sha512="$(sha512sum "$file" | awk '{print $1}')"
    printf '%s  %s\n' "$sha256" "$basename" > "$target_dir/${basename}.sha256"
    printf '%s  %s\n' "$sha512" "$basename" > "$target_dir/${basename}.sha512"
}

process_version() {
    local version="$1"
    local target_dir="$CHECKSUMS_DIR/$version"

    if [ ! -d "$target_dir" ]; then
        echo "SKIP $version: no $target_dir directory" >&2
        return
    fi

    local tmp
    tmp="$(mktemp -d)"
    # shellcheck disable=SC2064
    trap "rm -rf '$tmp'" RETURN

    for ext in tar.gz zip; do
        local archive="openemr-${version}.${ext}"
        local url; url="$(sf_url "$version" "$archive")"
        local file="$tmp/$archive"
        local sha1_file="$target_dir/${archive}.sha1"

        if [ ! -f "$sha1_file" ]; then
            echo "SKIP ${version}/${archive}: no .sha1 sidecar committed" >&2
            continue
        fi
        if [ -f "$target_dir/${archive}.sha256" ] && [ -f "$target_dir/${archive}.sha512" ]; then
            echo "SKIP ${version}/${archive}: .sha256 + .sha512 already exist"
            continue
        fi

        echo "GET  $archive  <-  $url"
        curl -sfL --retry 3 --retry-delay 2 -o "$file" "$url"
        verify_sha1 "$file" "$sha1_file"
        write_sidecars "$file" "$archive" "$target_dir"
        echo "WROTE ${target_dir}/${archive}.sha256"
        echo "WROTE ${target_dir}/${archive}.sha512"
        rm -f "$file"
    done
}

main() {
    local versions=("$@")
    if [ ${#versions[@]} -eq 0 ]; then
        # Auto-select every version dir under static/checksums/ that has
        # a .sha1 but is missing at least one of .sha256 or .sha512.
        while IFS= read -r dir; do
            local v; v="$(basename "$dir")"
            # Include the version if any .sha1 in the dir has a missing
            # .sha256 or .sha512 counterpart. Checking per-archive (not
            # per-dir) matters when a directory partially completed --
            # e.g. tar.gz has full sidecars but zip is missing sha256+512.
            for sha1 in "$dir"/*.sha1; do
                [ -e "$sha1" ] || continue
                local base="${sha1%.sha1}"
                if [ ! -f "$base.sha256" ] || [ ! -f "$base.sha512" ]; then
                    versions+=("$v")
                    break
                fi
            done
        done < <(find "$CHECKSUMS_DIR" -mindepth 1 -maxdepth 1 -type d | sort -V)
    fi

    if [ ${#versions[@]} -eq 0 ]; then
        echo "Nothing to do -- every version dir already has .sha256 + .sha512 sidecars."
        return
    fi

    echo "Processing ${#versions[@]} version(s): ${versions[*]}"
    echo
    for v in "${versions[@]}"; do
        process_version "$v"
        echo
    done
    echo "Done."
}

main "$@"
