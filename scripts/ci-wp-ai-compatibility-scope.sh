#!/usr/bin/env bash
set -euo pipefail

# Only skip for a known nonempty documentation diff in the checked-out PR
# merge commit. Stale/shallow checkouts and any Git error keep the matrix on.
docs_only=false
if [[ "${GITHUB_EVENT_NAME:-}" == pull_request && -n "${PR_BASE_SHA:-}" && -n "${PR_HEAD_SHA:-}" ]] \
	&& [[ "$(git rev-parse --verify HEAD^1)" == "$PR_BASE_SHA" && "$(git rev-parse --verify HEAD^2)" == "$PR_HEAD_SHA" ]]; then
	diff_status=0
	git diff --quiet --no-renames "$PR_BASE_SHA" HEAD -- || diff_status=$?
	if [[ "$diff_status" == 1 ]] && git diff --quiet --no-renames "$PR_BASE_SHA" HEAD -- \
		':(exclude,top)README.md' ':(exclude,top)AGENTS.md' ':(exclude,top,glob)docs/**/*.md'; then
		docs_only=true
	fi
fi
printf 'docs_only=%s\n' "$docs_only"
