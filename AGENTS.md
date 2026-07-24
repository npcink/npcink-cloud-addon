# Agent Notes

This repository is the standalone `npcink-cloud-addon` WordPress plugin.

Cross-project platform coordination starts from
`/Users/muze/gitee/npcink-workflow-toolbox/docs/platform/README.md`. This
repository remains the Cloud Addon transport owner; do not expand platform,
Core governance, Toolkit ability, Adapter channel, product-surface, billing, or
runtime truth rules here beyond addon-owned connector contracts.

## Boundaries

- Keep this addon a Cloud connector only.
- Do not add router, prompt, preset, approval, proposal, workflow/task queue,
  scheduler truth, workflow engine, billing truth, or WordPress write ownership.
- Bounded observability buffering and WP-Cron flushing are allowed only for
  opt-in, verified, metadata-only plugin monitoring uploads.
- Bounded Site Knowledge change buffering, WP-Cron flushing, local delivery
  consent, and explicit administrator delivery intents for Cloud-owned index
  operations are allowed only for public content delivery to Cloud Site
  Knowledge; Cloud owns index execution, lifecycle, and freshness policy.
- Do not reintroduce `/v1/runtime/workflows/runs`.
- Do not expose split credential fields in the UI.
- Do not print or log the stored `secret`.
- Do not make the settings page a second control plane.
- Read `docs/cloud-addon-complexity-budget.md` before expanding addon scope;
  keep security/boundary checks, but do not add product-control complexity.
- For WordPress AI plugin zh_CN compatibility strings, follow
  `docs/ai-plugin-localization-maintenance.md`; do not translate dynamic
  ability metadata in this addon.

## AI Development Rules

- Start AI-assisted work with `git status --short --branch` and a compact
  change envelope: target repositories, focused module, intended change,
  explicit non-goals, public contracts touched, expected files, files or areas
  that must not change, required gates, cross-repo matrix requirement, and
  rollback plan.
- Before staging, inspect `git status --short --branch` and `git diff --stat`.
  Stage only files changed for the current task. Do not use `git add -A` in a
  mixed worktree.
- Do not run `git reset --hard`, `git checkout -- .`, or equivalent destructive
  cleanup unless the user explicitly asks for that exact operation.
- Before committing, verify `git diff --cached --stat` and
  `git diff --cached --name-only`; after committing, verify
  `git show --name-status --stat HEAD`.
- Publish pull requests with `.github/pull_request_template.md` as the body
  contract. For non-interactive AI work, prepare a completed body file and run
  `composer pr:publish -- --title "<title>" --body-file <path>`. Do not replace
  the template with an ad hoc `gh pr create --body` that omits `Scope`,
  `Boundary`, `Verification`, or `Risk`.
- `composer pr:publish` may push only the current clean topic branch, creates
  the PR, and requests squash auto-merge after required checks. It deliberately
  does not delete local or remote branches because this repository commonly
  uses multiple worktrees.
- The cross-repository contract is
  `/Users/muze/gitee/npcink-workflow-toolbox/docs/platform/pr-publishing-standard-v1.md`.
- For multi-repo milestones, run the central matrix from
  `/Users/muze/gitee/npcink-workflow-toolbox` instead of copying the script into
  this addon: `composer quality:matrix` for status and
  `composer quality:matrix:run` before cross-repo closeout.

## Current Cloud Runtime Contract

Allowed runtime/read endpoints:

- `POST /v1/runtime/execute`
- `POST /v1/runtime/media/uploads` for bounded image source uploads only
- `POST /v1/runtime/media/jobs`
- `GET /v1/runs/{run_id}`
- `GET /v1/runs/{run_id}/result`
- `GET /v1/runs/nightly-inspection/recent`
- `POST /v1/runs/{run_id}/retry` for bounded runtime retry only
- `GET /v1/runtime/media/artifacts/{artifact_id}/download` for bounded verified delivery
- `POST /v1/runtime/media/artifacts/{artifact_id}/delivery-ack` after byte and image verification only
- `GET /v1/entitlements/current`
- `POST /v1/observability/plugin-events`
- `GET /v1/observability/plugin-summary`
- `POST /v1/agent-feedback/events`
- `GET /v1/agent-feedback/summary`
- `GET /health/live` for unsigned liveness

## Local Verification

Run before handing off changes:

```bash
composer run test:all
git diff --check
rg '/v1/runtime/workflows/runs|\b(?:wp_insert_post|wp_update_post|wp_insert_attachment|wp_update_attachment_metadata|update_post_meta|wp_set_post_terms|set_post_thumbnail|media_handle_sideload)\s*\(' --glob '*.php' --glob '!build/**' .
```

Also run `composer run smoke:playground` when a change affects plugin bootstrap,
activation, the public connector API, the default credential/connector state,
or the supported WordPress/PHP compatibility baseline. It is a disposable
SQLite/WASM compatibility gate, not a replacement for Local MySQL, Cloud,
browser, or production evidence. For an unrelated change, state why this gate
is not applicable in the handoff or PR verification record.

Documentation may mention forbidden concepts only to describe what this addon must not own.
