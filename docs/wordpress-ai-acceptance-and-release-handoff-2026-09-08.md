# WordPress AI Acceptance and Release Handoff

Date: 2026-09-08. Scope: Local WordPress acceptance and Addon documentation.
This is dated evidence, not current runtime or production authority.

Latest follow-up: image generation, native media import, and featured-image
adoption into the test draft passed later on the same date. See the final
section; earlier disabled-feature observations below remain historical facts.

## Baseline and Verified Evidence

- Local site: `http://magick-ai.local`, single site; official AI plugin 1.3.0.
- Addon baseline: `d1ddd86ebdb1e62853852177b17bfba8418ba2e2`, merged PR
  [#142](https://github.com/npcink/npcink-cloud-addon/pull/142).
- Cloud capability contract was merged in
  [#927](https://github.com/npcink/npcink-ai-cloud/pull/927).
- The installed Addon is linked to the original Addon checkout. Documentation
  changes in this handoff do not alter that runtime.
- Read-only verification found draft `281071`, still unpublished, with title
  `Npcink AI 插件系列上线 WordPress 官方插件库` and modification time
  `2026-09-08 12:51:00` UTC.
- Official log `18320642-b742-4360-963e-6dd96f1d2280` is persisted as
  `ai_client`, success, `openai / gpt-5.5`, 7302 ms. Its metadata identifies
  `run_21969d96e26041bb8ac5713ca6846c09` and omits input/output content.
- A fresh signed read of that Cloud run returned `succeeded`. The result
  endpoint was also readable. These reads did not execute a model.
- Earlier browser acceptance recorded generation, unchanged candidate adoption,
  draft save, and Cloud quality/journey receipt for this same run. This handoff
  rechecked persisted evidence; it did not repeat those browser actions.
- The active journey buffer was empty on this recheck. Earlier recovery backed
  up and quarantined fake browser runs, then the normal flush accepted 14 events
  (11 stored, 3 duplicates). An empty buffer does not prove future Cron delivery.

## Capability Recovery and Image Limitation

The cached snapshot dated `2026-09-08T13:06:11.056529Z` was correctly projected
as `unknown / snapshot_expired` for all three capabilities. One ordinary signed
refresh returned a snapshot at `2026-09-08T14:22:07.968920Z`, with text, image
generation, and vision all `configured` and `provider_call_performed=false`.
This proves configuration recovery, not successful image generation.

The official host option `wpai_feature_image-generation_enabled` was false.
`ai/title-generation` and `ai/alt-text-generation` were registered;
`ai/image-generation` was not. The installed host contains its own image
generation feature and Media page. The missing test entry is therefore a
disabled host feature, not proof of missing Cloud model configuration.

Use WordPress AI settings to enable the host feature when image generation is
part of the intended site workflow. Then verify the host's registered entry,
perform one useful bounded generation, inspect the official log and Cloud run,
import through the host media flow, and review/save the image in a draft.
Do not bypass the host toggle by adding an Addon registry or direct write path.
Image generation, import, and editor adoption remain unverified in this record.

## Repeatable Failure Acceptance

The following existing isolated behavior suites passed on this baseline:

- `php tests/behavior-entitlement-summary.php`: malformed/missing/expired
  evidence, failed refresh after backoff, successful signed recovery, missing
  credentials, entitlement/quota projection, and bounded readiness failures.
- `php tests/behavior-wordpress-ai-failure-projection.php`: signature and
  entitlement denial, provider errors even with upstream HTTP 200, idempotency
  conflicts, bounded error metadata, and credential redaction.
- `php tests/behavior-wordpress-ai-request-log-bridge.php`: official log types,
  text/image/vision correlation, opt-in behavior, and invalid output logged as
  error rather than success.

These are deterministic behavior checks. They do not claim browser usability
acceptance of every error, a live exhausted account, or an induced upstream
outage. Keep destructive fault injection out of the real site: use isolated
fixtures for denial/outage cases and reserve live calls for useful acceptance.

## Development Lessons

1. Track connection identity, fresh capability configuration, runtime success,
   and reviewed WordPress adoption as separate evidence. None implies the next.
2. Reuse a persisted real run when verifying logs and delivery. Extra model
   calls do not repair missing evidence about an existing run.
3. Match test doubles to the installed host contract. Official AI 1.3.0 accepts
   `ai_client` as the log type; text/image/vision belong in modality metadata.
4. Distinguish an installed feature, its enabled option, its registered ability,
   and its visible UI entry before attributing a missing action to Cloud.
5. Isolate fake browser run IDs from live telemetry. Preserve exact backup and
   quarantine evidence; never replay fake IDs through the normal Cloud queue.
6. An embedding call proves vector execution, not retrieved context injection.
   Read explicit context evidence for the same run before claiming injection.
7. Recheck shared preview identity at acceptance. Another task's candidate must
   not be overwritten or represented as the previously accepted revision.

## Release Envelope and Remaining Gates

Production was not changed. Strict Addon capability projection requires the
Cloud contract to be deployed first. Before a production proposal:

- Finish the active compiled-preview task and capture its clean-master M4
  acceptance; this handoff does not own that task or its runtime operations.
- Treat images as unavailable at the site-feature level until the intended
  host workflow is enabled and accepted, or explicitly exclude them from scope.
- Check browser recovery copy/actions for the intended release workflow.
- Keep context-injection evidence, Cron scheduling observation, and fake-run
  browser-test isolation visible as separate open follow-ups.
- Select exact Cloud and Addon release revisions, confirm checks and package
  compatibility, and name each prior deployed version as its rollback target.
  Do not assume all accumulated master changes belong in one release.
- Obtain explicit production authorization with runtime scope and rollback.
  For a capability-contract rollback, revert the strict Addon consumer first
  or keep the additive Cloud contract available until consumers are compatible.

This handoff does not claim complete production readiness. It preserves a
verified text path and an explicit image limitation without adding a second
test console, control plane, or WordPress writer.

## Continued Acceptance: Image and Connection Recovery

On the next acceptance pass, the authoring Mac had no listener at
`127.0.0.1:18010`. A signed entitlement read returned
`cloud_runtime_request_failed` with cURL error 7. The real WordPress AI settings
page showed a disabled global toggle and the valid-connector warning.

The existing `pnpm run m4:preview:auto` command, run from the stable operations
worktree, selected Pgy after LAN was unavailable and reported
`tunnel_ready=true`. No Cloud deployment or configuration change was needed.
The signed snapshot at `2026-09-08T15:21:24.786715Z` confirmed all three
capabilities. Reloading the same browser page removed the connector warning
and showed the previously enabled global AI toggle correctly. This validates
the actual browser disconnect/recovery path. Other denial/outage browser paths
remain covered only by deterministic behavior checks.

The official image-generation toggle was then enabled through its settings UI.
The native Media > Generate Image page accepted one prompt for a blue/green
editor-module illustration for the existing plugin-series draft. The returned
image was visually inspected, then saved with the native media-import action.

- Official log: `d95aab82-0db3-4c2f-a8f7-744c00aa0f67`, `ai_client`, success,
  7434 ms, configured provider ID `openai`, model `grok-imagine-image-quality`.
  The provider ID is connector evidence, not a claim about the model vendor.
- Cloud run: `run_7859b9d0f7eb4e6bb727cb94e351e110`; signed read confirmed
  `succeeded`, `fallback_used=false`, `provider_call_count=1`.
- Native media import displayed success and created attachment `281073`:
  JPEG, 1152 x 864, 89844 bytes.
- In the normal editor media picker, the attachment title was shortened to
  `WordPress AI 插件系列配图`; ALT was reviewed and replaced with
  `蓝绿色编辑器模块围绕文档图标的插画`. No additional ALT model call was made.
- The image was selected as the featured image for draft `281071`; the editor
  displayed the image and confirmed the draft was saved. A database read
  verified `_thumbnail_id=281073`, status `draft`, and body equality with
  original article `280982`. The original published article was not edited.

One image generation was executed; this does not test image editing, inline
image insertion, or a separate automated image-adoption telemetry contract.
The host image feature remains enabled. To reverse this acceptance setup, use
the host settings toggle and remove the featured image from the test draft;
the media attachment can be retained without publishing the article.

The existing observability Cron hook is registered as `hourly`,
`DISABLE_WP_CRON=false`, and the journey buffer was empty. This proves
configuration only, not an observed natural Cron delivery. No fake event was
inserted to manufacture that evidence.

The M4 owner reported PR #928 accepted on clean master
`e19afb17dafc1b17fbc9f650860365b9571561ed`; its auxiliary worktree was removed.
This task used the existing backend through a tunnel, with no sync or deploy.
The Cloud admin page required login in this browser, so the historical text
run's explicit context-injection evidence remains pending authorized admin
inspection. Do not infer it from the successful image run or embedding calls.
