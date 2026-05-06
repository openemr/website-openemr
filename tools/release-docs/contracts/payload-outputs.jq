# Project a normalized dispatch payload onto the GHA $GITHUB_OUTPUT
# `key=value` lines the rest of the workflow consumes. Keep this in sync
# with the steps that read `steps.payload.outputs.*`.
"event="        + .event,
"version="      + .data.version,
"branch="       + .data.branch,
"sha="          + .sha,
"prev_release=" + (.data.prev_release // ""),
"tag="          + (.data.tag // ""),
"files_json="   + ((._files // .data.files // []) | tojson),
"files_count="  + ((._files // .data.files // []) | length | tostring)
