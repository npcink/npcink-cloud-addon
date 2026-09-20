# Local Business Validation Plan v1

Status: active development validation guide. Date: 2026-09-20.

This plan turns the local WordPress AI acceptance flow into a repeatable
business check. It validates the user journey and the evidence behind it; it
does not create a second approval system, write owner, analytics product, or
Cloud control plane.

## Scope and safety

The default run uses a disposable local WordPress site, an isolated draft, and
the deterministic fake Provider path. It must not call a paid Provider, use
production content, publish a post, or change the production Cloud destination.
The real Provider lane is a separate, explicitly budgeted checkpoint.

The browser and local WordPress process run on the authoring Mac. Cloud runtime
behavior is exercised by the governed M4 preview. Record the exact Addon
worktree, Cloud revision, WordPress version, official WordPress AI version,
PHP version, and site URL for every run.

## Required evidence record

Create one dated record for each acceptance run. Do not store prompts, generated
content, credentials, user IDs, post IDs, or authorization headers in the
record. The minimum record is:

```text
run_date_utc:
local_site:
wordpress_version:
wordpress_ai_version:
addon_revision:
cloud_revision:
provider_mode: fake | real
cloud_execution_exercised: false | true
scenario_ids:
commands:
result: pass | fail | blocked | not-run
observed_run_ids:
observed_event_ids:
draft_cleanup: confirmed | failed
remaining_issue:
```

`pass` means every assertion for the declared scenario passed. A successful
HTTP response or an activated plugin is not business evidence by itself.

## Stage 0 — Freeze the test subject

Run the mounted-runtime check before opening the browser:

```bash
composer run local:runtime -- /absolute/path/to/wordpress
```

Confirm that the resolved plugin target is the intended clean worktree. Then
run the read-only browser preflight:

```bash
WP_BASE_URL="https://magick-ai.local" \
composer run smoke:wp-ai-text-browser:preflight
```

If either command points at an unexpected worktree, site, or plugin version,
stop the run. Do not repair the environment during a business acceptance run;
fix it, record the new revision, and restart from Stage 0.

Set the PHP executable, MySQL socket, and site scheme from the actual Local
installation using `WP_CLI_PHP`, `WP_DB_SOCKET`, and `WP_BASE_URL`. Preflight
also requires fresh cached text capability evidence. It does not refresh that
evidence or treat saved credentials as proof of model availability. Use the
existing connection check to refresh an expired snapshot. Do not rewrite the
capability cache to make a failing test pass.

## Stage 1 — Deterministic user journey

Use the fake Provider browser smoke against an isolated draft:

```bash
WP_AI_TEXT_FAKE_PROVIDER=1 \
composer run smoke:wp-ai-text-browser
```

The required journey is:

1. Open an isolated draft containing a sentinel paragraph.
2. Generate a title, summary, and whole-paragraph rewrite.
3. Inspect every suggestion before accepting anything.
4. Regenerate the first successful title suggestion.
5. Edit the title manually, insert the selected summary and rewrite, then save.
6. Confirm the database contains the selected values; separately reload the
   editor and confirm they render correctly.
7. Confirm that the sentinel paragraph and unrelated fields are unchanged.
8. In the separate data-path smoke, repeat the local-apply helper and confirm
   it is a no-op. The browser smoke does not prove this helper assertion.
9. Delete the disposable draft and confirm cleanup.

The stage passes only when there are no pre-save content writes, no accidental
publish, no cross-field changes, no pending fixture records, and no leftover
fake Provider filter or temporary option. The browser smoke locks autosave for
its fixture; normal WordPress autosave behavior needs a separate manual case.
A failed cleanup is a failed run,
even when the visible editor result looked correct.

The current fake mode intercepts Addon HTTP locally and blocks quality-event
uploads. It proves editor behavior, not Cloud execution or signed ingestion.
Use `WP_AI_TEXT_VALIDATE_QUALITY=1` only when monitoring is already enabled to
validate fixture quality records before cleanup. Report cancel/reload, concurrent
tabs, and the other Stage 2 cases separately: they are requirements, not claims
about what the existing browser runner covers.

## Stage 2 — Business edge cases

Run these cases against a fresh fixture or a reset draft. Each case must have a
stable scenario ID and a clear expected result.

| Scenario | Expected business result |
| --- | --- |
| Empty or very short input | Clear validation or bounded result; original content remains safe |
| Chinese, English, mixed punctuation, and long input | Text remains intact and the result is displayed in the correct field |
| Cancel or leave without saving | No AI suggestion is adopted |
| Generate A, then regenerate B | B is the current candidate; lifecycle evidence distinguishes both generations |
| Manual edit after generation | The edited value is preserved; exact AI adoption is not guessed |
| Timeout, 429, 5xx, malformed, or reasoning-only output | User sees a failure state; no silent protocol downgrade or content write |
| Two tabs or delayed responses | Older work cannot overwrite the newer generation |
| Insufficient capability or permission | The action is denied and no suggestion or write is created |

For every failed, retried, expired, or unattributed result, keep any available
Cloud run correlation. A rejection before Cloud dispatch has no Cloud run;
do not invent one. Unknown attribution is an explicit outcome, not a successful
adoption and not a rejection claim.

## Stage 3 — End-to-end evidence and natural delivery

After the fake browser journey passes, verify the real Addon-to-Cloud path with
an isolated M4 test lane. This needs a separate Cloud-side deterministic
Provider fixture and real run IDs, or a separately budgeted real checkpoint;
the current local fake runner cannot provide this evidence. Correlate the user action, Cloud run, presented
generation, and local save separately. The following facts must remain
distinct:

- a Cloud run completed;
- a suggestion was presented;
- a user accepted or edited it;
- WordPress saved the selected value.

For observability delivery, use the read-only inspector before and after one
normal local action:

```bash
composer run local:journey:inspect
```

Wait for the ordinary Cron schedule and inspect again. A manually flushed
buffer, an empty buffer, or a scheduled hook is not proof of natural delivery.
Never create fake live queue IDs.

## Stage 4 — Real Provider checkpoint

Only start this stage after Stages 0–2 pass. When no Cloud-side deterministic
fixture is available, use the same approved real run for Stage 3 correlation
and Stage 4 semantic review, without duplicating Provider calls.
Before the first call, declare the
Provider, model, number of calls, maximum retries, cost ceiling, and stop
conditions. Use a disposable local site and non-sensitive text. A small first
checkpoint should cover one title, one summary, and one rewrite, with no
automatic retry beyond the declared budget.

Review each result for semantic correctness:

- title: relevant, usable, and free of instruction-like wrapper text;
- summary: faithful to the source and free of invented facts;
- rewrite: preserves meaning and changes only the requested selection.

Protocol checks must reject invalid envelopes according to the Responses ADR.
Human review must separately reject semantically unusable results, including
instruction-like output; the adapter cannot guarantee semantic correctness.
Do not report technical success as business success. Do not open a natural-traffic or production
pilot based on one successful Provider response.

## Stage 5 — Release-shaped rehearsal

Build the exact candidate package and repeat the core journey after a clean
install and an upgrade from the previous supported version. Verify the Cloud
contract and Addon consumer in the compatible order, exercise the rollback
path in an isolated environment, and record any dependency or database-version
parity gap. This stage is required before treating local business evidence as
release evidence.

## Exit criteria

The local business validation phase is ready for a separately approved pilot
only when:

- the mounted source and all runtime revisions are recorded;
- deterministic title, summary, rewrite, save, cancel, retry, and cleanup
  scenarios pass;
- no unauthorized WordPress write, publish, cross-site attribution, or
  credential disclosure occurs;
- failed and unknown outcomes remain visible and correlated;
- the real Provider checkpoint passes the semantic review; a blocked checkpoint
  must be recorded and prevents pilot readiness;
- the release-shaped package and upgrade path have evidence;
- every skipped scenario and known limitation is listed.

These criteria reduce release risk; they do not prove that upstream model
quality is permanently stable or that a production cohort is ready.
