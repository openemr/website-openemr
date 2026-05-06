# Build a canonical dispatch envelope from the workflow_dispatch inputs
# payload. Mirrors the shape that arrives via repository_dispatch so the
# rest of the workflow can treat both event sources identically.
#
# Args:
#   --arg actor   GitHub actor name (e.g. $GITHUB_ACTOR)
#   --arg repo    Source repo for the synthetic dispatch (e.g. openemr/openemr)
#   --arg ts      ISO-8601 dispatched_at timestamp
#
# `_files` is a sidecar carrying the synthetic input's files JSON; the
# workflow strips it before validating against dispatch.schema.json and
# uses it to shape the optional sub-dispatch to website-openemr-files.
{
  event: .event,
  repo:  $repo,
  sha:   .sha,
  actor: $actor,
  dispatched_at: $ts,
  data: (
    if   .event == "openemr-tag"
    then {tag: .tag, branch: .branch, version: .version}
    else {branch: .branch, version: .version, prev_release: .prev_release}
    end
  ),
  _files: (.files | fromjson? // [])
}
