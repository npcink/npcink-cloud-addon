# Refresh status and recovery closeout — 2026-10-14

## Scope and boundary

This closeout covers the administrator refresh feedback reported during the
2026-10-13/14 review: stale entitlement warnings, missing Chinese fallback
text, Site Knowledge recovery state that did not replace the old view, and
metadata delivery responses that could claim success without an accepted
count. The addon remains a Cloud connector. The work does not add workflow,
router, prompt, preset, billing, approval, scheduler truth, index lifecycle,
or WordPress content-write ownership.

The implementation keeps one shared status-fragment path for the affected
administrator reads. A successful read replaces the complete server-rendered
fragment; a failed read preserves independent warnings and the last known
value. Recovery controls appear only for a real failure, are hidden after a
successful replacement, and remain informational while a queued or active
delivery is still running. Unknown or malformed service and accepted-count
values fail closed. The retry and reconciliation paths preserve buffered work
and use the existing Cloud-owned delivery contract.

## Reusable engineering rules

1. Treat an asynchronous refresh as a replacement of one owned fragment, not
   as a collection of ad hoc DOM mutations. This prevents a successful read
   from leaving a stale banner, badge, waiting count, or recovery button
   behind.
2. Keep independent local facts independent. A connection warning, a Site
   Knowledge delivery failure, and a monitoring projection failure must each
   survive an unrelated successful read until its own evidence is refreshed.
3. Accept a remote write response only when the response contains the field
   that proves acceptance. Missing, non-numeric, or impossible counts retain
   the local buffer and surface recovery instead of reporting success.
4. Use server-rendered translated strings as the normal browser source and
   keep a small last-resort fallback for unavailable configuration. Dynamic
   ability metadata remains untranslated and outside this addon’s catalog.
5. Test the recovery state machine, not only the first request: duplicate
   clicks, blocked scheduling, uncertain retries, stale callbacks, missing
   fragments, and successful replacement must all have explicit assertions.
6. A complexity ratchet is a guardrail, not a reason to remove boundary
   checks. The settings page ratchet is 2283 lines after this closeout and the
   matching budget document is updated with the reason for the increase.

## Verification and evidence

The final local HEAD for this closeout is recorded by the release PR. Before
publication, the following passed on the clean topic worktree:

- `composer validate --no-check-publish`
- `composer run test:all`
- `composer run stan`
- `composer run check:wporg`
- `composer run check:js`
- `composer run i18n:check`
- `composer run plugin-check:release`
- `composer run package:release`
- `composer run package:verify`
- `git diff --check`

The Playground smoke gate is not applicable: bootstrap, activation, the
public connector API, default credential state, and the supported
WordPress/PHP compatibility baseline are unchanged.

The advisory OCR supplier completed file discovery but its grouping request
timed out and the fallback per-file dispatch produced no result before the
bounded wait was ended. This is a supplier availability failure, not evidence
that the code review passed. The local review record must retain this fact and
the green local gates separately; do not describe the release as “all checks
green” until the complete PR rollup confirms it.

## Closeout hygiene

Only the current topic branch may be pushed or published. After squash merge,
fetch with prune, fast-forward the local default branch, remove this topic
branch locally and remotely, and archive only the worktree created for this
task. Other OCR worktrees and branches belong to separate sessions and must be
left untouched. A final `git status --short --branch` must be clean before the
conversation is closed.
