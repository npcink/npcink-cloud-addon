# Security and Performance Closeout - 2026-06-27

Status: merged into `master`.

This document records the security/performance hardening work completed for the
standalone `npcink-cloud-addon` plugin on 2026-06-27, plus the repository state
left for future feature development.

## Scope Closed

The work focused on keeping this addon a bounded Cloud connector while reducing
security and performance risk in existing transport/detail surfaces.

Changes shipped:

- Aligned the documented Cloud runtime/read endpoint allowlist with the runtime
  client and static contract tests.
- Added local response-size limits for JSON Cloud runtime responses and raw
  derivative artifact preview downloads.
- Made oversized or locally truncated Cloud JSON responses fail closed.
- Restricted local media derivative upload file descriptors to WordPress uploads
  or local temp directories after `realpath()` resolution.
- Changed the settings page entitlement summary to use cached data during page
  render, avoiding a synchronous Cloud request on admin page load.
- Avoided duplicate Site Knowledge buffer/status option writes when the buffered
  public post ids are unchanged.
- Fixed release-gate issues reported by Plugin Check: escaped WordPress AI
  connector error messages and documented the bounded `debug_backtrace()` use.
- Added behavior/static coverage for the new boundaries.

Related localization work included fixed WordPress AI editor zh_CN compatibility
coverage for admin/editor UI strings. Dynamic ability metadata still must not be
translated in this addon.

## Boundary Decisions

The closeout preserved the existing addon boundary:

- The addon remains a Cloud connector and transport/detail layer.
- It does not own router, prompt, preset, approval, proposal, workflow engine,
  scheduler truth, billing truth, or WordPress write authority.
- Site Knowledge changes remain public content-change delivery hints only;
  Cloud owns index lifecycle and freshness policy.
- Media derivative adoption remains preview-only and declares
  `final_write_owner=local_wordpress_host`.
- Cloud result handling remains fail-closed and endpoint-allowlisted.

The most important review question stays unchanged:

```text
Is this transport/detail, or is it control/write truth?
```

Transport/detail may live here when bounded and endpoint-allowlisted. Control or
write truth belongs in local Core, Adapter, or Cloud service-plane code.

## Verification Performed

Local repository gates:

```sh
composer run test:all
composer run check:wporg
composer run ai:i18n:audit
git diff --check
rg "/v1/runtime/workflows/runs|wp_insert_post|wp_update_post" --glob '*.php' --glob '!build/**' .
```

Release/plugin checks:

```sh
composer run plugin-check:release
```

The Plugin Check run used a temporary local WordPress plugin symlink because the
local `npcink` site did not already mount `npcink-cloud-addon`. The symlink was
removed after the check.

Cross-repo matrix:

```sh
cd "$NPCINK_WORKFLOW_TOOLBOX"
composer quality:matrix
composer quality:matrix:run
```

All matrix gates passed. Some adjacent repositories already had unrelated dirty
worktrees during the matrix run; no files outside this addon were changed for
this closeout.

Notes:

- `composer run ai:i18n:audit` exited with status 0 and reported existing local
  WordPress AI plugin shim candidates. Treat that as audit output, not a failure
  of this closeout.
- `plugin-check:release` initially surfaced `.DS_Store`, unescaped exception
  messages, and `debug_backtrace()` warnings; those were resolved before merge.

## Git and PR History

Local commits before PR merge:

```text
92b0c54 Add AI plugin editor localization coverage
b4df139 Harden Cloud addon transport boundaries
```

Pull request:

```text
https://github.com/muze-page/npcink-cloud-addon/pull/9
```

PR #9 was first created as a draft PR from
`codex/cloud-addon-release-hardening`. The initial PR branch was created through
the GitHub Git Data API because local git HTTPS to `github.com:443` temporarily
timed out and the available SSH key authenticated as a deploy key for another
repository. After git HTTPS recovered, the PR branch was force-updated with
`--force-with-lease` so the remote branch matched the local two-commit history
and could be managed with standard git CLI commands.

The PR body contract required these headings:

- `Scope`
- `Boundary`
- `Verification`
- `Risk`

After the PR body was corrected, both required checks passed:

- `PHP contracts`
- `PR body contract`

The PR was marked ready and merged through GitHub because direct push to
`master` is protected:

```text
GH006: Protected branch update failed for refs/heads/master.
Changes must be made through a pull request.
```

GitHub merged PR #9 with rebase merge. Remote `master` ended at:

```text
b253e0d Harden Cloud addon transport boundaries
```

Because git fetch was briefly unstable after merge, the remote rebase commit
objects were locally reconstructed and SHA-checked from GitHub API metadata, then
local `master` and `origin/master` were aligned to the remote state.

## Final Repository State

At closeout:

- Current branch: `master`
- Local `master`: aligned with `origin/master`
- Worktree: clean
- Merged PR: #9
- Removed local/remote feature branch:
  `codex/cloud-addon-release-hardening`
- Temporary local WordPress plugin symlink: removed
- `.DS_Store`: absent
- Standard `git fetch --prune origin`: working again
- Remaining open GitHub PR at the time: Dependabot
  `dependabot/github_actions/actions/checkout-7`

## Starting New Feature Work

Start future feature development from the clean `master` state:

```sh
git switch master
git pull
git switch -c codex/your-feature-name
```

Before handing off a new code change, run the local gates from `AGENTS.md`:

```sh
composer run test:all
git diff --check
rg "/v1/runtime/workflows/runs|wp_insert_post|wp_update_post" --glob '*.php' --glob '!build/**' .
```

For release-sensitive changes, also run:

```sh
composer run check:wporg
composer run plugin-check:release
```

For cross-repo milestones, run the central matrix from
a checkout of `npcink-workflow-toolbox`:

```sh
composer quality:matrix
composer quality:matrix:run
```
