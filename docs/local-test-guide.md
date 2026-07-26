# Local Test Guide

## Static Checks

Run:

```bash
composer run test:all
git diff --check
```

`tests/run.php` is only an aggregate runner:

- `tests/static-contracts.php` checks source and docs for boundary contracts.
- `tests/behavior-media-derivative.php` calls public PHP APIs with WordPress
  stubs to prove fail-closed behavior.
- `tests/behavior-site-knowledge-change-bridge.php` calls the Site Knowledge
  bridge handlers with WordPress stubs to prove approved comments buffer their
  parent public post for Cloud refresh transport.
- `tests/helpers.php` contains shared assertions, stubs, and fixtures.

Before expanding addon scope, read `docs/cloud-addon-complexity-budget.md`.

Boundary checks:

```bash
rg "/v1/runtime/workflows/runs" --glob '*.php' --glob '!build/**' .
rg "wp_insert_post|wp_update_post|wp_delete_post" --glob '*.php' --glob '!build/**' .
rg "approval truth|proposal truth|billing truth|workflow engine" docs README.md AGENTS.md
```

Expected:

- No `/v1/runtime/workflows/runs`.
- No WordPress post write calls.
- No ownership of approval, proposal, billing, workflow, or scheduler truth.
- Observability upload uses only a bounded metadata buffer and approved
  observability endpoints.

Media derivative contract checks:

- ability payloads are guarded against credentials, Authorization, and signed
  headers before dispatch;
- unverified Cloud credentials fail closed;
- expired Cloud artifacts cannot produce adoption/proposal payloads;
- default proposal payloads are preview-only and do not replace the original
  attachment file;
- proposal payloads declare `final_write_owner=local_wordpress_host`.

## Disposable Playground Smoke

Run this fast compatibility gate when a change affects plugin bootstrap,
activation, the public connector API, the default credential/connector state,
or the supported WordPress/PHP baseline:

```bash
composer run smoke:playground
```

The command starts one temporary local WordPress Playground instance, mounting
the current checkout as `npcink-cloud-addon`. It pins the Playground CLI,
WordPress, and PHP versions in `scripts/smoke-playground.sh`, activates the
plugin through `tests/playground/blueprint.json`, calls an ephemeral local
assertion route, then terminates the instance and removes its temporary files.

Prerequisites are Node.js 20.18 or later, `npx`, `curl`, and a free local TCP
port (default `9417`). Set `NPCINK_PLAYGROUND_PORT` only when that port is in
use. Do not mount a checkout containing credentials or point this smoke at
production data.

Expected evidence:

- the Addon activates in a fresh SQLite/WASM WordPress instance;
- its public connector API is available;
- default state is fail-closed: no credentials, runtime client, verified runtime
  client, or synthetic WordPress AI connector marker;
- the smoke itself makes no Cloud request and exposes no secret value.

This is not evidence for MySQL behavior, native PHP extensions, media import,
Cloud authorization or signed transport, Portal/Access behavior, browser review,
or production networking. Keep the Local MySQL/Cloud, browser, and release
gates in their existing tiers. The command is intentionally outside
`composer run test:all` and GitHub CI because it downloads the pinned Playground
and WordPress runtime; record it in the PR whenever the triggering scope above
applies, or state why it is not applicable.

## WordPress Smoke Test

Site:

`https://npcink.local/`

Steps:

1. Log in with a local administrator account.
2. Activate `Npcink Cloud Addon`.
3. Open `Npcink > Cloud Addon`.
4. Use the Cloud authorization action to add the current site.
5. Confirm the callback saves the returned connection key and immediately verifies.
6. Confirm failed verification shows a clear error and does not mark the settings as verified.
7. Confirm successful verification shows `configured_valid` without split credential identifiers.
8. View page source and confirm the stored connection key is not present.
9. Confirm entitlement read failures show `unavailable` and are not presented as usable entitlement.

## WordPress AI Editor Smoke Test

For the local `magick-ai.local` development site with Cloud settings already
verified and WordPress AI features enabled, run:

```bash
composer run smoke:wp-ai-editor
```

The command uses WP-CLI against `WP_PATH`, defaulting to
`/Users/muze/Local Sites/magick-ai/app/public`. Override `WP_AI_SMOKE_USER`,
`WP_PATH`, `WP_CLI_BIN`, `WP_CLI_PHP`, or `WP_DB_SOCKET` when the local site
differs.

This data-path smoke creates one deterministic temporary draft and calls exactly
the current editor abilities `ai/title-generation`, `ai/summarization`, and
`ai/content-resizing`. The resizing input is the selected whole `core/paragraph` block's
content; it is not an arbitrary selected text range.
After all three suggestions return, the smoke first proves that the raw title,
raw body, draft status, and revision IDs are unchanged, so Cloud remains
suggestion-only and performs zero WordPress writes.

The script then uses an explicit local acceptance helper to apply the returned
title, one generated summary block, and the selected whole `core/paragraph`
block rewrite to the isolated draft. This is a local data-path acceptance; it
does not simulate browser review or Core audit. The helper is called a second
time to prove the already-applied state is a no-op with no new revision. A
`finally` block force-deletes the temporary draft and confirms cleanup even
when an assertion fails.

Expected:

- the deterministic temporary post starts and remains `draft` until cleanup;
- all three Cloud-backed ability calls return before any local application;
- title, body, status, and revision IDs remain unchanged before acceptance;
- local acceptance applies the title and exactly one generated summary block;
- only the target paragraph block is rewritten, while the non-target sentinel
  paragraph remains byte-for-byte unchanged;
- the second local apply helper call is a no-op and creates no revision;
- the temporary draft is permanently deleted and the deletion is confirmed;
- no publish action is performed.

Use this after changing the Cloud WordPress AI connector, runtime output
normalization, or the local AI-plugin compatibility shim. Browser review is a
separate product/UI acceptance activity; this command is only the repeatable
editor data-path regression gate described above.

## WordPress AI Text Browser Acceptance

Before spending a provider call or creating a disposable draft, run the
read-only prerequisite check:

```bash
WP_BASE_URL="https://magick-ai.local" \
composer run smoke:wp-ai-text-browser:preflight
```

The preflight verifies the Local/development environment, site origin,
official WordPress AI 1.2.0 plugin, verified Addon connector, required feature
flags, and an editable administrator. It does not create a draft, start a
browser, invoke an AI provider, or write WordPress data. Use
`composer run smoke:wp-ai-text-browser -- --help` to inspect the two modes
without connecting to WordPress.

Run this opt-in browser gate against a disposable local/development WordPress
site with the official WordPress AI 1.2.0 plugin, a verified Cloud Addon
connection, and only title generation, summarization, and content resizing
enabled:

```bash
NODE_PATH="/Applications/ChatGPT.app/Contents/Resources/cua_node/lib/node_modules" \
HEADLESS=1 \
WP_BASE_URL="https://magick-ai.local" \
WP_AI_TEXT_ARTIFACT_DIR="/tmp/npcink-cloud-addon-p5-b3" \
WP_AI_TEXT_SUMMARY_PATH="/tmp/npcink-cloud-addon-p5-b3-summary.json" \
composer run smoke:wp-ai-text-browser
```

Use an installed Playwright module instead of `NODE_PATH` when available.
`WP_PATH`, `WP_CLI_BIN`, `WP_CLI_PHP`, `WP_DB_SOCKET`, and
`WP_AI_SMOKE_USER` have the same override purpose as the data-path smoke.

The browser gate refuses non-local hosts and non-local/development WordPress
environments. It creates an isolated draft, locks autosave, uses the real AI
1.2.0 editor controls to review title, summary, and one whole-paragraph
rephrase, proves zero post writes before the explicit Save/Update click, then
verifies one local save, unchanged sentinel blocks, revision evidence, draft
status, and forced fixture cleanup. Screenshots and the optional JSON summary
serve different evidence purposes: the JSON contains only hashes and bounded
request metadata, while screenshots intentionally show the disposable fixture
and reviewed suggestions. Keep screenshots in a controlled temporary directory
and never run this gate with real sensitive content. The gate is intentionally
outside `composer test:all` because it requires a configured local WordPress
site, browser runtime, and live Cloud provider.

When the optional WordPress AI request log is enabled, the Addon records
`cloud_run_id` only inside metadata-only request context for correlation. It
does not store prompt or output previews, and that identifier is not local
approval, Core audit, persistence, or WordPress write truth.

## WordPress AI Generation Reference A/B Evaluation

Run a read-only paired evaluation across real published posts:

```bash
composer run eval:wp-ai-generation-reference
```

Override the bounded sample with `WP_AI_EVAL_POST_IDS=1,2,3`. The evaluator
alternates baseline/reference order, runs title, excerpt, summary, meta
description, and classification abilities, and restores the original local
reference permission in a `finally` block. It does not update posts, publish
content, or apply taxonomy terms.

Without explicit ids, the evaluator deterministically selects the five most
recent published posts whose plain-text content has at least 200 characters.
Use `WP_AI_EVAL_POST_LIMIT=15` to collect a larger sample (maximum 30), and
`WP_AI_EVAL_MIN_POSTS` to fail early if the site has too few eligible posts.
For a promotion-shaped collection, keep at least four tasks, at least three
successful A/B pairs per task, and at least 15 task pairs overall (for example,
three posts across all five tasks). The artifact records whether it is ready
for blind judging without claiming that the reference variant is better.

Write the same clean JSON emitted on stdout directly to an Eval Lab input file:

```bash
WP_AI_EVAL_POST_LIMIT=15 \
WP_AI_EVAL_OUTPUT_JSON=/absolute/path/to/npcink-eval-lab/generation-context/generated/wp-ai-generation-reference-eval.json \
composer run eval:wp-ai-generation-reference
```

File output is opt-in and atomic. Progress and file-path messages go to stderr,
so stdout remains machine-readable JSON. The A/B switch is a process-local
WordPress option override; the evaluator never persists a settings change, and
the Composer command disables its normal five-minute process timeout for a
complete provider-backed run.

The evaluator waits 3200 ms between provider calls by default to stay within
common OpenAI-compatible provider rate limits. Override
`WP_AI_EVAL_DELAY_MS` only when the configured provider permits a different
request rate.

The JSON result records non-empty output reliability, historical length
distance, existing taxonomy reuse, historical-text similarity, boilerplate,
and numbers not present in the current article. These are operational proxy
metrics for regression detection; final product quality still requires blind
human preference review.

## Cloud Contract Smoke Test

With valid Cloud credentials:

- `probe_connectivity()` calls `/health/live` and signed `/v1/entitlements/current`.
- `execute_runtime()` calls `/v1/runtime/execute`.
- `get_run()` calls `/v1/runs/{run_id}`.
- `get_run_result()` calls `/v1/runs/{run_id}/result`.
- `get_current_entitlement()` only reads `/v1/entitlements/current`.
- When the Cloud response includes `entitlement.pro_cloud_runtime`, the local
  entitlement summary preserves the Pro Cloud Runtime quota detail as read-only
  display data.
- `send_observability_events()` only writes metadata-only events to
  `/v1/observability/plugin-events`.
- `get_observability_summary()` only reads `/v1/observability/plugin-summary`.

Stats and entitlement data are read projections only. They must not become local billing truth, local quota engines, scheduler truth, or WordPress write authority.
Observability summaries are dashboard projections only. They must not become
Core audit, proposal, approval, execution, billing, or workflow truth.
