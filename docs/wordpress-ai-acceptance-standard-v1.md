# WordPress AI Acceptance Standard v1

Status: active Addon engineering guide. Date: 2026-09-09.

## Scope and Ownership

This guide governs Addon evidence collection and acceptance handoffs. Cloud
owns runtime execution and capability evidence; the host owns feature toggles,
abilities, review, media import, and final WordPress writes. Use the existing
host UI and logs. Do not introduce another registry, test console, approval
system, or writer to make acceptance easier.

## Diagnose in Order

1. Confirm the actual site, active plugin versions, source revision, and Cloud
   destination. A familiar URL does not identify its deployed source.
2. Check transport reachability separately from credential identity. For a
   localhost SSH destination, verify that its listener and tunnel are alive.
3. Read signed, fresh capability evidence. Connection success alone does not
   prove text, image, or vision configuration. Expired or failed evidence is
   unknown; preserve the credential marker and use the existing refresh path.
4. Inspect the host feature toggle, registered ability, and visible entry.
   An installed feature may be disabled despite configured Cloud models.
5. Perform a useful bounded operation through the intended host entry. Declare
   the generation budget first; reuse existing real runs for subsequent reads.
6. Correlate the actual model, run, official request log, delivered output, and
   reviewed draft save. Report each proven step separately.

## Evidence Rules

- Record site, source revision, UTC time, run ID, official log ID, draft or
  attachment ID, and the exact scope of the check. Do not store secrets or
  private provider input in acceptance records.
- For official AI 1.3.0, the bridge uses `ai_client`; text/image/vision are
  modality metadata. Test doubles must reject unsupported host log types.
- An HTTP success is insufficient: validate text output or image bytes before
  logging success. Keep Cloud run correlation on invalid-output failures.
- Image acceptance includes generation, visual inspection, native import, and
  reviewed editor adoption/save. A thumbnail proves neither image editing nor
  a separate automated adoption-telemetry contract.
- An embedding call proves retrieval execution, not context injection. Require
  explicit bounded context evidence for that same run. Admin login cannot
  reveal evidence that was never persisted. A new projection does not backfill
  historical runs; validate its behavior with focused tests and an appropriate
  future real operation.
- An empty telemetry buffer proves neither scheduled execution nor successful
  upload. Separate manual flush receipts, Cron registration, and naturally
  observed delivery. Never manufacture fake run IDs in a live queue.
- Use isolated fixtures for denied entitlements, invalid credentials, and
  provider failures. Do not disrupt the real site to create test evidence.

## Natural Cron Test Tool

Use the read-only inspector before and after a normal real WordPress action:

```bash
composer run local:journey:inspect
```

It auto-detects the Local MySQL socket and reports the journey buffer event IDs,
the hourly Cron hook, next scheduled time, monitoring state, and last upload
summary. It never runs Cron, calls `flush_buffer()`, writes options, creates
events, or calls a model. Set `WP_PATH`, `WP_CLI_BIN`, `WP_CLI_PHP`, or
`WP_DB_SOCKET` when the site is not the default Local site.

The acceptance sequence is: inspect with an empty buffer; use the site normally
to create one real event; inspect again and record its `event_id`/`run_id`; wait
for the ordinary Cron cycle; inspect again; then correlate that same ID with the
Cloud customer-journey receipt. Empty buffer, an hourly schedule, or a previous
manual flush is not natural-delivery evidence.

## Shared Work and Historical Records

Read related task records and compare run IDs before repeating acceptance.
One run reused by several tasks remains one piece of evidence. Distinguish
the task that performed an action from a later task that verified it.

Keep one dated acceptance record and link related summaries to it. Preserve
historical observations but add a prominent latest-state note when recovery
supersedes them. Do not leave an old unchecked item as apparent current truth.
An uncommitted local fix is not a merged fix; a merged fix is not production.

Do not stage another active task's dirty code or documents merely to obtain a
clean checkout. Use a locked focused worktree. Transfer ownership only after
the receiving task acknowledges it; a suggested owner is not a handoff.

## Closeout and Release

Review the final diff, run the appropriate gates, publish using the repository
PR template/publisher, and verify all checks plus merge state. Update the local
master without overwriting user work. Remove only the exact clean merged task
worktree; delete branches separately after proving their content is preserved.

For foreground preview tunnels, state their lifetime explicitly. Do not claim
the site remains usable after closing its only transport. Hand off the existing
foreground command when continued use is required; do not silently install a
daemon or switch the site's Cloud destination.

Deploy the additive Cloud capability contract before distributing its strict
Addon consumer. For rollback, preserve that contract until consumers are
compatible or revert the strict consumer first. Production requires a separate
intentional release scope, exact revisions, rollback targets, and authorization.

## Source Evidence

See the [dated acceptance record](wordpress-ai-acceptance-and-release-handoff-2026-09-08.md)
for text and image runs, recovery observations, merged PRs, and remaining gaps.
