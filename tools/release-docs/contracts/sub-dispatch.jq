# Build the cross-repo dispatch envelope the workflow POSTs to
# website-openemr-files when an inbound payload carries non-empty files.
# Forwards the file list byte-for-byte; rebuilds the envelope locally so
# the downstream consumer validates against the same vendored schema.
#
# Args:
#   --arg sha     Source-commit sha   (e.g. $GITHUB_SHA)
#   --arg actor   Triggering actor    (e.g. $GITHUB_ACTOR)
#   --arg repo    Source repo         (e.g. $GITHUB_REPOSITORY)
#   --arg ts      ISO-8601 dispatched_at timestamp
#   --arg version Release version
#   --arg branch  Release branch
{
  event_type: "openemr-docs-binaries",
  client_payload: {
    event: "openemr-docs-binaries",
    repo:  $repo,
    sha:   $sha,
    actor: $actor,
    dispatched_at: $ts,
    data: {
      version: $version,
      branch:  $branch,
      files:   ((._files // .data.files // []) | map(tostring))
    }
  }
}
