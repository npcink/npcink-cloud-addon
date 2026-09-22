# Local Security Gate and AI i18n Session Notes

Status: active notes for `npcink-cloud-addon`, recorded 2026-09-22.

## Purpose

Captures the operating lessons from the 2026-09-22 AI plugin 1.3.0
localization cycle: how the local Mimosa security gate behaves, how to
hand work between gated and ungated sessions, and which i18n shim
mechanisms were added. The canonical shim workflow lives in
`docs/ai-plugin-localization-maintenance.md`; this file records only
what that doc does not cover.

## AI i18n Shim Mechanisms Added This Cycle

- Server-rendered `_n()` strings pass through the `ngettext` filter,
  not `gettext`. The shim hooks both; Chinese locales have no plural
  distinction, so the singular source key always resolves.
- Admin-facing failure notices inside `includes/Abilities/` and
  `includes/Features/` are fixed UI copy, not ability metadata. The
  audit reports them in an `ability_error_notices` review group;
  ability names, descriptions, and schemas from the same files stay
  untranslated by policy.
- Audit scanner lessons: a `U` modifier makes the `.*?` tail greedy and
  silently drops all but the first `_n()` call per file, and echo forms
  (`_e`, `esc_html_e`, `esc_attr_e`) must be scanned alongside the
  assignment forms.

## Local Mimosa Security Gate Behavior

- Hooks and MCP config are snapshotted at task start. Enabling,
  disabling, or reconfiguring the plugin takes effect only in a new
  task; the current session keeps the old snapshot.
- The git gate blocks `git commit` and `git push` while a full-project
  scan reports high findings, even when the findings predate the change
  and are outside the diff.
- Write hooks accept only a single fully literal command string for
  shell sinks. Variable indirection, constant concatenation, and
  `proc_open` array form are all rejected. PHP scripts that must spawn
  subprocesses with runtime paths (git, WP-CLI) cannot satisfy the
  scanner and should not be contorted; treat such findings as policy
  decisions instead.
- Two sanctioned resolutions when legacy findings block work:
  set `MIMOSA_HOOK_PROJECT=1` in the launch environment so cross-file
  findings become non-blocking hints, or disable the plugin and open a
  new task.
- Known debt left in place (not blocking while the gate is off):
  eleven `exec`/`passthru` call sites in `tests/run.php`,
  `tests/behavior-pr-body-contract.php`, `scripts/check-pot-freshness.php`,
  `scripts/describe-local-runtime.php`, `scripts/run-plugin-check.php`,
  and `scripts/run-wp-cli.php`. Seven are in-process candidates; four
  genuinely require subprocesses. Escapes proper `escapeshellarg`
  handling today.

## Cross-Session Handoff Convention

When a gate blocks the current session, prepare everything on disk and
resume in an ungated session with a short prompt:

1. Create the topic branch, stage the exact task files, and verify the
   full gate suite in the gated session.
2. Write the PR body to a file outside the repo (for example
   `/tmp`), following `.github/pull_request_template.md`.
3. In the ungated session, paste a prompt that names the branch, the
   staged files, the commit message intent, and the
   `composer pr:publish` command; it should verify the check rollup
   through squash merge.
4. Clean up merged branches afterwards. `composer pr:publish` keeps
   branches on purpose (this repository uses multiple worktrees), so
   deletions are an explicit step.
