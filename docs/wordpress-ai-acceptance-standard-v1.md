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

The compatibility lanes are declared in
`tests/fixtures/wp-ai-compatibility-matrix.json`: WordPress 7.0.4 with
WordPress AI 1.3.0 is the primary stable lane, WordPress 7.1.1 with AI 1.2.0
is the reviewed regression lane, and WordPress 7.1.1 with upstream `develop`
is a visible non-blocking warning lane. The
`composer run smoke:wp-ai-compatibility -- <lane>` runner boots each lane in
WordPress Playground, installs the pinned AI artifact, activates the Addon,
and checks the bounded Abilities and connector seam. The Cloud contract remains
the Npcink connector contract in every lane; upstream class names and UI hooks
stay inside the Addon. The complete browser/provider/save flow remains an
opt-in local acceptance check because it needs a verified Cloud connection and
must not manufacture paid traffic in CI.

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
- Editor-assist quality events include the locally installed official WordPress
  AI version when the reviewed `ai/ai.php` header is available. Missing version
  data remains an explicit unknown compatibility bucket; it never becomes a
  guessed version or a Cloud routing input.
- Use isolated fixtures for denied entitlements, invalid credentials, and
  provider failures. Do not disrupt the real site to create test evidence.

## Natural Cron Test Tool

Use the read-only inspector before and after a normal real WordPress action:

```bash
composer run local:journey:inspect
```

It auto-detects the Local MySQL socket and reports the journey buffer event IDs,
their expected site-scoped Cloud hash IDs, the hourly Cron hook, next scheduled
time, monitoring state, and last upload summary. It never prints the Cloud site
ID or credentials, runs Cron, calls `flush_buffer()`, writes options, creates
events, or calls a model. Set `WP_PATH`, `WP_CLI_BIN`, `WP_CLI_PHP`, or
`WP_DB_SOCKET` when the site is not the default Local site.

The acceptance sequence is: inspect with an empty buffer; use the site normally
to create one real event; inspect again and record its `event_id`/`run_id`; wait
for the ordinary Cron cycle; inspect again; then correlate the reported
`expected_cloud_event_id` and `run_id` with Cloud storage. Cloud hashes raw event
IDs as `SHA-256(site_id|event_id)` and does not persist the raw value. Empty
buffer, an hourly schedule, or a previous manual flush is not natural-delivery
evidence.

## WordPress AI Acceptance and Quality Report

### Scenario identity

The acceptance runner records a stable `scenario_id` for every case. The
scenario identifies the concrete input and contract path being exercised; it
is intentionally more specific than the official Ability name. The same
Ability may therefore appear more than once when its runtime semantics differ,
for example:

- `classification-post-tag-existing-only` for existing post tags;
- `classification-category-existing-only` for existing categories.

Consumers must de-duplicate and report cases by `scenario_id`, while still
retaining the Ability name for grouping. A missing or duplicated scenario ID is
an acceptance-contract error. Filtering by `WP_AI_ACCEPTANCE_ABILITIES` is
Ability-based and must retain every matching scenario. This prevents a
successful tag check from masking an untested category path and keeps the
official WordPress plugin response format unchanged.

The ordinary-text baseline uses the same public connector path for title,
excerpt, SEO description, summary, resizing, translation, Slug, and editorial
assistance. Translation has separate plain-text and Gutenberg-block scenarios;
the latter records both expected and returned block counts. Slug coverage
includes English and mixed-language titles and requires lowercase ASCII
hyphenated candidates. These are deterministic transport and structure checks,
not an automatic judgment that the generated prose is publishable.

Structured coverage keeps the official Ability semantics visible in the
development report. Taxonomy scenarios record the requested taxonomy,
strategy, and maximum suggestion count; `existing_only` rejects any returned
term marked as new, and output above the requested limit fails with a distinct
`classification_too_many` code. Editorial notes require a review type and
actionable text, while editorial updates and comment replies remain suggestion
strings. Comment analysis validates the returned comment ID, sentiment, and
bounded toxicity score. These checks never change the official plugin response
or write to WordPress; they only make malformed or semantically empty
structured results diagnosable before human review.

Visual coverage keeps media boundaries explicit. Image-prompt scenarios require
a non-empty prompt string and retain only context/style presence metadata. Alt
text scenarios record whether the input came from an attachment or URL without
persisting the media bytes; non-decorative images must return non-empty alt text,
while decorative images may intentionally return an empty string. The runner
never imports media, updates attachment metadata, or inserts an image.

Use the combined local command when the candidate Addon is mounted in the
target WordPress site:

```bash
composer run acceptance:wp-ai-provider:quality
```

It writes the read-only Addon report and the Eval Lab quality report under
`wordpress-ai-provider/generated/`. The command returns `0` only when the
acceptance report and deterministic quality report both pass; `2` means the
report requires review or the Addon could not close its evidence state; `1`
means a deterministic or command failure. It never saves, publishes, or
applies a WordPress result.

For offline CI and regression tests, provide an existing acceptance report:

```bash
WP_AI_ACCEPTANCE_INPUT=/path/to/acceptance.json \
WP_AI_ACCEPTANCE_REPORT=/tmp/wp-ai-acceptance.json \
WP_AI_ACCEPTANCE_QUALITY_REPORT=/tmp/wp-ai-quality.json \
composer run acceptance:wp-ai-provider:quality
```

This mode never invokes WP-CLI or a Provider. Keep real local acceptance and
offline CI evidence as separate evidence states.

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
