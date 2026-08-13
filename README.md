# Npcink Cloud Addon

Standalone WordPress plugin for connecting a local Npcink installation to `npcink-cloud`.

The addon is a thin Cloud connector. It stores the Cloud Base URL and the Cloud API Key signing credentials returned by Cloud site authorization, sends signed runtime requests, reads health and entitlement status, transports opt-in metadata-only plugin observability and Agent feedback data, bridges public Site Knowledge change hints to Cloud, and exposes a minimal PHP interface for local plugins. Signing credentials are persisted as one authenticated encrypted envelope rather than plaintext option fields.

Cross-project platform coordination starts from
`/Users/muze/gitee/npcink-workflow-toolbox/docs/platform/README.md`. This
repository remains authoritative only for Cloud Addon connector contracts and
bounded signed transport.

## Engineering Decisions and Standards

- [Runtime seam closeout and engineering standard](docs/runtime-seam-closeout-and-engineering-standard-2026-08-13.md)
  records the consumer-migration, public-seam removal, Playground stabilization,
  verification ladder, publication workflow, and reusable closeout checklist.
- [ADR-001: Remove the public concrete runtime client seam](docs/decisions/001-remove-concrete-runtime-client-seam.md)
  records why the helper was removed during the pre-user phase and the required
  preconditions for future API removals.
- [Development experience and methodology](docs/development-experience-and-methodology-2026-08-08.md)
  contains the broader cross-repository diagnosis and release methodology.

## Scope

The addon owns:

- Cloud Base URL and authenticated encrypted signing credential storage.
- Cloud site authorization callback exchange and `mak1_{base64url(json)}` parsing.
- HMAC signing, trace headers, idempotency headers, and Cloud error mapping.
- Connectivity probing with `/health/live` and a signed Cloud read.
- Runtime and read projection calls:
  - `POST /v1/runtime/execute`
  - `POST /v1/runtime/media/uploads` for bounded image source uploads
  - `POST /v1/runtime/media/jobs`
  - `GET /v1/runs/{run_id}`
  - `GET /v1/runs/{run_id}/result`
  - `GET /v1/runs/nightly-inspection/recent`
  - `POST /v1/runs/{run_id}/retry`
  - `GET /v1/runtime/media/artifacts/{artifact_id}/download`
  - `POST /v1/runtime/media/artifacts/{artifact_id}/delivery-ack`
  - `GET /v1/entitlements/current`
- Opt-in plugin observability transport:
  - `POST /v1/observability/plugin-events`
  - `GET /v1/observability/plugin-summary`
  - `wordpress_monitoring_state.v1` boolean state projection; WordPress
    remains the setting owner
- Bounded Agent feedback event transport and read-only quality projection:
  - `POST /v1/agent-feedback/events`
  - `GET /v1/agent-feedback/summary`
- Site Knowledge public content change bridge through `POST /v1/runtime/execute`, including a bounded settings-page status and manual public refresh transport.
- Toolbox Site Knowledge runtime bridge through `POST /v1/runtime/execute`.
- An `Advanced and troubleshooting > Runtime runs` section for read-only Nightly Inspection recent/status/result detail and nonce-protected Cloud-owned retry requests.
- Bounded image context evidence transport through `POST /v1/runtime/execute`.
- Bounded WordPress AI connector scene runtime through `POST /v1/runtime/execute`.
- `Npcink AI > Cloud Addon` when Workflow Toolbox is active, or
  `Settings > Npcink Cloud Addon` when installed standalone.

The addon does not own approval truth, proposal truth, WordPress writes,
workflow/task queue control, scheduler truth, billing truth, prompt ownership,
router ownership, preset ownership, or Site Knowledge index lifecycle. Its local
observability and Site Knowledge buffers are only bounded delivery buffers for
Cloud transport; they are not audit, execution, billing, indexing, or workflow
truth.

Monitoring permission remains a WordPress-local setting. The addon projects
only its boolean state through the existing observability endpoint. Enabled
sites refresh the projection through the existing hourly observability flush,
and a local permission change sends the new state immediately. No content,
prompt, generated text, user identity, or credential value is included.

## Disposable Playground Smoke

Run `composer run smoke:playground` to boot a clean, SQLite-backed WordPress
Playground instance with this checkout mounted as the addon. The smoke pins the
Playground CLI, WordPress, and PHP versions and verifies plugin activation,
the public connector API, and the default fail-closed state (no credentials,
no runtime client, and no synthetic WordPress AI connector marker).

It uses no Cloud credentials, production data, or external Cloud request. It
is a fast addon compatibility gate only; it does not replace the Local
MySQL/Cloud media E2E, Portal authorization, browser acceptance, or production
network verification.

The entitlement summary preserves Cloud `pro_cloud_runtime` detail such as
Nightly Site Inspection run limits, used and remaining runs, batch limits,
retention, payload modes, and quota-exhausted state. These fields are read-only
display projections for local plugins such as Toolbox; the addon does not turn
them into local billing truth, a local quota engine, scheduler truth, or a
WordPress write path.

## Public PHP Interface

```php
npcink_cloud_addon_is_configured(): bool
npcink_cloud_addon_get_connection_state(): array
npcink_cloud_addon_verified_runtime_client(): ?Npcink_Cloud_Runtime_Client
npcink_cloud_addon_get_manual_readiness_result(): array
npcink_cloud_addon_dispatch_media_derivative_cloud_request(array $ability_response, array $source_artifact, string $trace_id = '', string $idempotency_key = '')
npcink_cloud_addon_request_image_context_evidence(array $image_context_evidence_request, string $trace_id = '', string $idempotency_key = '')
npcink_cloud_addon_execute_wordpress_ai_connector_runtime(array $request, string $trace_id = '', string $idempotency_key = '')
npcink_cloud_addon_execute_wordpress_ai_image_generation_runtime(array $request, string $trace_id = '', string $idempotency_key = '')
npcink_cloud_addon_execute_toolbox_image_generation_runtime(array $request, string $trace_id = '', string $idempotency_key = '')
npcink_cloud_addon_execute_toolbox_audio_generation_runtime(array $request, string $trace_id = '', string $idempotency_key = '')
npcink_cloud_addon_execute_toolbox_site_ops_cloud_analysis_runtime(array $request, string $trace_id = '', string $idempotency_key = '')
npcink_cloud_addon_execute_toolbox_web_search_runtime(array $request, string $trace_id = '', string $idempotency_key = '')
npcink_cloud_addon_execute_toolbox_image_source_runtime(array $request, string $trace_id = '', string $idempotency_key = '')
npcink_cloud_addon_dispatch_site_knowledge_runtime(array $runtime_payload, string $ability_name = '', string $contract_version = '')
npcink_cloud_addon_build_media_derivative_proposal_payload(array $ability_response, array $cloud_result, array $derivative_artifact)
npcink_cloud_addon_receive_media_derivative_artifact(array $artifact, string $trace_id = '')
npcink_cloud_addon_site_knowledge_change_bridge_health(): array
```

`Npcink_Cloud_Runtime_Client` exposes:

```php
probe_connectivity(): array
manual_readiness_test(): array
execute_runtime(array $payload, string $trace_id = '', string $idempotency_key = '')
execute_wordpress_ai_connector_runtime(array $request, string $trace_id = '', string $idempotency_key = '')
execute_wordpress_ai_image_generation_runtime(array $request, string $trace_id = '', string $idempotency_key = '')
execute_toolbox_image_generation_runtime(array $request, string $trace_id = '', string $idempotency_key = '')
execute_toolbox_audio_generation_runtime(array $request, string $trace_id = '', string $idempotency_key = '')
execute_toolbox_site_ops_cloud_analysis_runtime(array $request, string $trace_id = '', string $idempotency_key = '')
execute_toolbox_web_search_runtime(array $request, string $trace_id = '', string $idempotency_key = '')
execute_toolbox_image_source_runtime(array $request, string $trace_id = '', string $idempotency_key = '')
request_image_context_evidence(array $image_context_evidence_request, string $trace_id = '', string $idempotency_key = '')
upload_media_artifact(array $file, string $trace_id = '', string $idempotency_key = '')
create_media_job(array $payload, string $trace_id = '', string $idempotency_key = '')
get_run(string $run_id, string $trace_id = '')
get_run_result(string $run_id, string $trace_id = '')
get_recent_nightly_inspection_runs(int $limit = 5, string $trace_id = '')
retry_run(string $run_id, array $payload = array(), string $trace_id = '', string $idempotency_key = '')
pull_media_artifact(string $artifact_id, string $trace_id = '')
acknowledge_media_artifact_delivery(string $artifact_id, array $payload, string $trace_id = '', string $idempotency_key = '')
get_current_entitlement(string $trace_id = '')
send_observability_events(array $events, string $trace_id = '', string $idempotency_key = '')
send_agent_feedback_event(array $payload, string $trace_id = '', string $idempotency_key = '')
get_agent_feedback_summary(int $window_hours = 24, string $trace_id = '')
get_observability_summary(int $window_hours = 24, string $trace_id = '')
```

The low-level signed request method is private and endpoint-allowlisted. New
callers should use the named methods above instead of sending arbitrary Cloud
paths through the addon.

All Cloud HTTP calls share one local outbound policy. Production targets must
resolve only to public IP addresses, signed requests never follow redirects,
TLS verification remains enabled, JSON responses must declare a JSON media
type, and response bodies are capped by request class. Exact loopback targets
are available only when WordPress explicitly reports a local environment or a
local-development constant opts in.

Hostname validation is performed before dispatch and WordPress safe HTTP
validation runs again at dispatch, but the connector does not pin a DNS answer
to a transport connection. Operators must therefore configure trusted Cloud
hostnames and DNS; sub-second DNS rebinding remains a documented residual risk.

`manual_readiness_test()` reuses the existing `/health/live` plus signed
`GET /v1/entitlements/current` probe and returns
`cloud_addon_readiness_result.v1` with `status`, `bounded_status`,
`connector_slot`, `connector_diagnostic_category`,
`credential_slot_readiness`, `signed_transport_status`,
`service_liveness_status`, `owner_label`, `blocked_reason`,
`next_safe_action`, `copyable_support_facts`, `write_posture=read_only`, and
`tested_at`. It also includes `diagnostic_panel_groups`, a five-group read-only
projection for local configuration, Cloud connectivity, signed transport,
entitlement readiness, and bounded support facts. Each group carries only a
bounded status, severity, owner label, blocked reason, next safe action, and
non-secret support facts. The slot statuses are non-secret support detail for the local
connector slot, credential-slot completeness, public service liveness, and the
signed entitlement read only. They do not create runtime work, queues,
registries, approvals, provider logs, billing truth, or WordPress writes. The
admin Diagnostics page runs this test only from the explicit administrator
`Run readiness test` action; rendering the page does not perform a live probe
or signed Cloud read.

## Image Context Evidence Transport

The addon can consume a Toolbox-generated
`image_context_evidence_request.v1` artifact for weak media ALT/caption
metadata. It validates the request as suggestion-only, strips it to bounded
public media URLs or same-site short-TTL Artifact references and metadata,
sends it through `POST /v1/runtime/execute`, and
normalizes a Cloud response into `image_context_evidence.v1`.
The normalized evidence preserves bounded visual summaries, subject tags,
visible text, ALT/caption basis, confidence, and uncertainty flags while
reducing structured runtime source metadata to a non-secret evidence label and
model id.

For the bounded Local WordPress pilot, run
`wp eval-file scripts/eval-local-image-context-artifact-pilot.php 20
--user=<administrator>`. The script selects at most 20 eligible local
JPEG/PNG/WebP attachments, uploads each through the existing image-only
Artifact endpoint, and sends only Artifact ids to the evidence runtime. It
does not expose the Local site, persist a media index, or write WordPress.

Cloud owns the visual recognition runtime, provider routing, model execution,
and result generation. The addon does not run a local vision model, create a
queue, create a Core proposal, approve anything, or write media metadata.
Returned evidence is candidate basis only and must still be visually confirmed
by the local operator before any future governed apply path.

Use `npcink_cloud_addon_get_connection_state()` for status and local permission
checks. It intentionally omits credential identifiers and the stored secret.

`npcink_cloud_addon_get_settings()` remains callable only as a deprecated
compatibility seam because it returns server-side signing settings. The former
concrete runtime-client helper has been removed; all maintained Npcink consumers
must use the scenario-specific public helpers listed above.

Toolbox consumers should use the scenario-specific facades for content support,
site helpers, Nightly Inspection submit/read/retry, Site Media visual-source
uploads, Agent Feedback, and entitlement reads. These functions keep the
concrete signing client private and fail closed to their fixed contracts.

The public PHP settings shape still contains `site_id`, `key_id`, and `secret`
for server-side signing, but the WordPress option contains only a versioned
authenticated credential envelope. Its key is derived from the WordPress
authentication salt. Changing security salts makes the existing envelope
unreadable and requires reconnecting the site; decryption or authentication
failure is treated as unconfigured. This protects database-at-rest credentials,
but it does not protect against a fully compromised server that can execute
WordPress code and access the salts.

## WordPress AI Connector Runtime

After Cloud settings pass Save and Verify, the addon registers one fixed
`Npcink Cloud` connector on the WordPress Connectors surface. The connector uses
a synthetic local marker setting so the WordPress AI plugin can recognize the
verified Cloud configuration without exposing the stored Cloud secret or adding
split credential fields.

The WordPress AI connector is enabled by default after a successful connection.
Administrators can turn it off at any time from the Overview tab under Local
permissions.

The addon also exposes
`npcink_cloud_addon_execute_wordpress_ai_connector_runtime()` as a narrow seam
for text connector/provider calls, plus
`npcink_cloud_addon_execute_wordpress_ai_image_generation_runtime()` for the
WordPress AI image generation feature. Both are WordPress scene runtimes, not
generic chat APIs, image provider proxies, or OpenAI-compatible provider
proxies.

The local `Reference site content during generation` permission is off by
default. When an administrator enables it, the scene-gated text model adds one
task-bound `site_knowledge_reference` hint to title and summary requests. Cloud may use existing Site Knowledge as
hidden style context or existing taxonomy vocabulary, while the WordPress AI
plugin continues to receive only the ordinary task result. The addon does not
receive or display source text, taxonomy evidence, chunks, similarity scores,
or vector details, and it does not own the result or any WordPress write.

`npcink_cloud_addon_execute_toolbox_image_generation_runtime()` is the bounded
transport seam for Toolbox AI image candidate generation. It accepts one
reviewed text prompt and small generation options, dispatches through Cloud
`npcink-cloud/generate-image`, and returns the Cloud runtime response for
Toolbox to normalize into `image_candidate.v1`. The addon does not render the
Toolbox recommendation UI, store generated candidate history, import media,
create proposals, approve anything, or set featured images.

`npcink_cloud_addon_execute_toolbox_audio_generation_runtime()` is the bounded
transport seam for Toolbox article audio candidate generation. It accepts only
reviewed narration or audio-summary requests, dispatches through Cloud
`npcink-toolbox/generate-audio`, and returns the Cloud runtime response for
Toolbox to normalize into audio candidates. The addon does not import audio,
write playback metadata, create adoption plans, or run audio refresh jobs.

`npcink_cloud_addon_execute_toolbox_site_ops_cloud_analysis_runtime()` is the
bounded transport seam for optional Toolbox Site Check Cloud detail. It accepts
only `site_ops_cloud_analysis_request.v1` packets that are runtime-detail,
suggestion-only, and no-write, dispatches through Cloud
`npcink-toolbox/analyze-site-ops`, and returns the Cloud runtime response for
Toolbox to render as review-only detail. The addon does not create a local run
table, scheduler, Core proposal, or WordPress write path.

`npcink_cloud_addon_execute_toolbox_web_search_runtime()` is the bounded
transport seam for Toolbox managed web search evidence. It accepts only
`web_search.v1` query packets, dispatches through Cloud
`npcink-cloud/web-search`, and returns the Cloud runtime response for Toolbox
to normalize as `web_search_results`. The addon does not store local search
provider keys, create proposals, or write WordPress content.

`npcink_cloud_addon_execute_toolbox_image_source_runtime()` is the bounded
transport seam for Toolbox image-source candidates. It accepts only
`image_source_cloud_request.v1` packets, dispatches through Cloud
`npcink-toolbox/search-image-source`, and returns the Cloud runtime response for
Toolbox to normalize into `image_candidate.v1` source candidates. The addon
does not import media, set featured images, write attribution, or create Core
proposals.

When the PHP AI Client is available, the addon registers scene-gated text,
vision, and image models. The text model only forwards calls that originate from known
WordPress AI plugin Ability classes, such as title, excerpt, metadata, summary,
classification, moderation, or rewrite. The vision model only forwards
WordPress AI alt-text generation calls with an editable local image attachment.
It captures the attachment id through WordPress's post-validation,
post-permission `wp_before_execute_ability` hook, validates the attachment and
local upload path, binds the opened file handle to the checked file metadata,
detects MIME from the bytes actually read, sends bounded JPEG, PNG, or WebP
bytes to the named short-TTL Cloud upload endpoint, then executes
`alt_text_suggest` with the returned same-site `source_artifact_id`. It never
forwards an attachment URL, Data URL, caller-supplied base64, or arbitrary file
path. The image model only forwards
text-to-image calls from the WordPress AI image generation feature and rejects
reference-image refinement. Direct `wp_ai_client_prompt()` usage outside
supported scenes is rejected before a Cloud request is made.

The registered `npcink-cloud-scene-text`, `npcink-cloud-scene-vision`, and
`npcink-cloud-scene-image` entries are WordPress AI scene wrapper models, not
direct provider model ids. They are added to the WordPress AI preferred model
lists only after Cloud settings pass Save and Verify. The addon does not expose
bottom-level provider model selection; Cloud hosted runtime profiles choose the
underlying provider/model. The bounded vision wrapper is only for
`alt_text_suggest`; it does not make the addon a generic vision provider,
router, media metadata writer, or approval owner.

The current upstream WordPress AI alt-text ability still materializes its
local image as a Data URL before it calls the selected AI Client model. The
addon intentionally does not intercept `wp_pre_execute_ability` or replace that
ability callback, because doing so would duplicate the upstream ability's input
validation, permission, and result truth. Removing that transient upstream
base64 allocation requires an upstream attachment-reference model seam; the
Addon transport itself never sends or persists the Data URL.

The transport request uses the platform-neutral
`cloud_connector_runtime.v1` envelope with
`ability_name=npcink-cloud/connector-runtime`, `channel=editor`, a verified
top-level `site_id`, and an input connector identity containing canonical
`site_url`, `platform_kind=wordpress`, `connector_id=npcink-cloud-addon`, the
active connector version, and `suggestion_only=true`. WordPress-specific task
semantics live only in the nested `wordpress_operation.v1` contract. Text tasks
use `execution_kind=text`; the bounded alt-text scene uses
`execution_kind=vision`.

`title_generation`, `content_summary`, and `content_rewrite` send the actual
AI Client user message as `source_text`, with an optional
`system_instruction`. They do not send legacy `prompt`, `post_title`, or
`post_excerpt` fields. Embedded content tags remain opaque source text. A
successful response must expose `cloud_connector_result.v1`,
`suggestion_only=true`, `connector_id=npcink-cloud-addon`, and a matching
`wordpress_operation.v1` task before text is read from
`response.data.result.output.output_text`; the addon does not dual-read legacy
result shapes.

This helper rejects generic chat or provider-control shapes such as `messages`,
`conversation_id`, `session_id`, `thread_id`, `tools`, `tool_calls`, `functions`,
`function_call`, `stream`, credentials, cookies, nonces, and signed headers. It
also clamps timeout to 60 seconds, retention, and retry values to the
lightweight scene runtime limits.

The addon also carries a bounded zh_CN compatibility shim for high-traffic
WordPress AI plugin admin/editor UI strings. Maintenance rules and the future
one-command audit contract are documented in
`docs/ai-plugin-localization-maintenance.md`. Do not turn that shim into a full
language pack or translate dynamic ability metadata in this addon.

## Media Derivative Transport

The addon can consume the read-only
`npcink-abilities-toolkit/build-media-derivative-cloud-request` ability output as a transport
input. It validates that the ability payload has no Cloud credentials,
Authorization data, or signed headers, requires verified Cloud settings, and
uploads bounded local image bytes through `/v1/runtime/media/uploads`, then
dispatches one artifact-referenced job through `/v1/runtime/media/jobs`.

The local host or Adapter still owns the ability call, local source file access,
short TTL source artifact creation, Core proposal creation, UI display,
approval, record, replace, rollback, and all WordPress writes. The addon helper
only returns a Core-ready proposal payload with
`final_write_owner=local_wordpress_host`; it does not persist or approve the
proposal.

Source media can be sent either as a local upload descriptor (`path`, `bytes`,
or `content`) or as a same-site short TTL Cloud artifact id. Optional
aspect-ratio crop plans in `cloud_job_payload.crop` are forwarded as bounded
Cloud runtime options. Optional watermarks require `cloud_job_payload.watermark`
in the ability response; the fifth dispatch parameter can then provide a
watermark upload descriptor or a short TTL watermark artifact id.

Local upload descriptors accept exactly one canonical byte source: `path`,
`bytes`, or `content`. The only other accepted keys are `filename` and
`mime_type`; every other key fails closed before Cloud transport. Removed
aliases `file_path`, `tmp_name`, and `name` retain a dedicated rejection error.

Expired Cloud artifacts are rejected before proposal adoption payloads are
built. The default action is preview-only and original attachment files are not
replaced by default.

Media job dispatch and run polling expose one exact eight-field status
projection. Its `artifact` is always empty, even when the status is
`succeeded`; failed and canceled states retain only bounded lifecycle error
facts. The exact Cloud 12-field artifact is parsed only by the separate result
read before it is projected into the local 11-field proposal artifact. Both
descriptors enforce an 8192-pixel maximum axis and a 16777216-pixel maximum
area.

For local adoption, the addon accepts only the exact 11-field local proposal
artifact, pulls bytes through the explicit signed
`GET /v1/runtime/media/artifacts/{artifact_id}/download` endpoint, verifies the
required delivery headers, byte length, SHA-256, MIME, dimensions, and decoded
image, and only then sends
`POST /v1/runtime/media/artifacts/{artifact_id}/delivery-ack`. The receive
helper returns exact verified-transfer evidence plus the Cloud ACK projection;
its top-level expiry remains exactly the reviewed local artifact expiry. It does not persist
the artifact, create an artifact registry, or write WordPress media.

## Observability Transport

Administrators may enable Cloud monitoring after Cloud settings verify. When
enabled, the addon listens for local `npcink_observability_event` metadata,
stores a bounded local observability buffer, and flushes buffered metadata to
Cloud. wp-admin shows only local buffer or upload errors when action is needed;
Cloud observability aggregates and Agent feedback quality detail remain
Cloud-owned data surfaces and are not copied into the local settings page.

Allowed uploaded fields are limited to operational metadata such as plugin
slug/version, event kind, status, timing, error code, route, proposal id,
ability id, correlation id, and counts.

Monitoring does not upload prompts, generated content, article body content,
media bytes, raw request/response payloads, provider credentials, Cloud API
secrets, passwords, cookies, nonces, Authorization headers, database names,
table names, or filesystem paths.

Cloud observability summaries are dashboard projections only. They must not be
used to approve proposals, change Core status, execute WordPress writes, or
configure router, prompt, or preset behavior.

## Site Knowledge Change Bridge

When Cloud settings are verified, the addon listens for public post/page and
approved comment changes only after an administrator explicitly enables Site
Knowledge delivery, stores a bounded local delivery buffer, and sends a
Cloud Site Knowledge refresh request through the existing
`POST /v1/runtime/execute` runtime contract.

The bridge only sends public content manifests and affected WordPress post ids
with `write_posture=suggestion_only`. Cloud remains the Site Knowledge vector,
index, freshness, and collection lifecycle owner. The addon does not create a
local index, decide stale-index policy, register a workflow engine, own scheduler
truth, or perform WordPress writes.

`npcink_cloud_addon_site_knowledge_change_bridge_health()` returns the stable
`site_knowledge_change_bridge_status.v1` projection. Host plugins should expose
that payload as `change_bridge` and prefer `buffer_count` for bounded delivery
buffer depth. `buffer_count` is local transport durability only; it is not queue
truth, freshness truth, index lifecycle truth, or Cloud diagnostics truth.

## Site Knowledge Runtime Bridge

The addon registers the `npcink_toolbox_site_knowledge_cloud_request` filter so
Toolbox can keep the operator UI and ability buttons while this addon owns the
signed Cloud transport detail. The bridge accepts only:

- `npcink-cloud/site-knowledge-search` with `site_knowledge_search.v1`
- `npcink-cloud/site-knowledge-status` with `site_knowledge_status.v1`
- `npcink-cloud/site-knowledge-sync` with `site_knowledge_sync.v1`

All requests must remain `write_posture=suggestion_only` and use
`POST /v1/runtime/execute`. The bridge does not create local indexing jobs,
collection lifecycle state, approval records, proposal records, or WordPress
writes.
The local sync transport accepts only `sync_mode=refresh`; rebuild, delete,
stale-index policy, embedding-provider, and collection lifecycle operations
belong in Cloud Site Knowledge. See
[`docs/site-knowledge-vector-operations.md`](docs/site-knowledge-vector-operations.md).

## Settings Page

Admin path:

`Npcink AI > Cloud Addon` (or `Settings > Npcink Cloud Addon` standalone)

The default flow opens Cloud Portal site authorization with a `return_url` and
state token. After Cloud returns a code, the addon exchanges it at
`/portal/v1/addon-connections/exchange`, stores the returned Cloud API Key
wrapper and base URL, and verifies the signed connection immediately.

Cloud Base URL must use `https://` unless it points to an exact local
development host (`localhost`, `127.0.0.1`, or `::1`) and WordPress explicitly
reports a local environment (or the local-request opt-in constant is enabled).
Site Knowledge delivery is disabled until an administrator explicitly enables
that local consent. Timeout and manual recovery key
entry are kept in `Connection Management > Manual fallback` for local debugging
or authorization outages.

When verified, the page opens `Overview` first. It shows plan/entitlement and
only surfaces monitoring or Site Knowledge summaries when local action is
needed. WordPress AI connector exposure and Site Knowledge delivery remain the
primary immediate-save permissions; generation reference is shown only when
delivery is enabled, and metadata-only monitoring consent is folded under
`More local permissions`. The
`Advanced and troubleshooting` entry is the Cloud Addon-side
replacement for the old Toolbox Cloud Checks / Troubleshooting Checks entry:
it shows compact connection checks, account/usage projection, attention-only
local monitoring upload state, connection recovery, and `Runtime runs` detail. It does not recreate
Toolbox product tools for Cloud search, image source search, provider
operations, or task execution.

`Advanced and troubleshooting > Runtime runs` is the low-frequency home for Nightly Inspection Cloud run detail that used to crowd Toolbox advanced surfaces. Its default entitlement projection is limited to nightly-run availability and retention. Manual run-ID lookup stays folded; recent runs, one-run status/result reads, and bounded Cloud retry remain available. It does not submit scheduled reviews, build local snapshots, create Core proposals, own retry queues, or write WordPress data.

The Pro Cloud Runtime projection also exposes contract reuse detail: Cloud owns
runtime/detail, Toolbox owns product buttons, Core owns proposal handoff,
Adapter owns execution profiles, and Toolkit owns ability contracts. The addon
is signed transport and read-only detail only; it adds no registry, scheduler
truth, approval store, queue, or write executor.

The Site Knowledge tab keeps delivery, buffered public changes, last delivery,
and manual public content refresh visible. Errors appear only when present.
Index operations use an explicit `Manage index` entry, while local error and
WP-Cron recovery facts appear under `Technical delivery details` only when
action is needed. Cloud owns index execution, rebuild/delete
handling, freshness policy, collection lifecycle, and deep diagnostics. Toolbox consumes
Site Knowledge results in fixed best-practice buttons instead of owning index
management UI.

The settings page never displays split signing credentials and does not provide
split credential editing.

The admin page scope is documented in
[`docs/admin-surface-standard.md`](docs/admin-surface-standard.md). The Cloud
site connection flow history is summarized in
[`docs/cloud-site-connection-flow-history.md`](docs/cloud-site-connection-flow-history.md).
Current reference-plugin learning notes for the connector surface are recorded
in [`docs/cloud-addon-reference-notes-2026-07.md`](docs/cloud-addon-reference-notes-2026-07.md).
The current signed-transport contract reuse observation is recorded in
[`docs/cloud-addon-contract-reuse-readiness-2026-07-08.md`](docs/cloud-addon-contract-reuse-readiness-2026-07-08.md).

## Repository Management

GitHub is the primary remote for ongoing development:

`https://github.com/muze-page/npcink-cloud-addon`

Do not keep or publish through Gitee remotes for current development. New work
should branch, review, and merge through GitHub. Keep `master` as the default
branch unless the CI and release process are intentionally migrated.

For a clean topic branch, publish a non-interactive PR with the checked-in body
contract and request squash auto-merge after required checks:

```bash
composer pr:publish -- \
  --title "fix: describe the focused change" \
  --body-file /absolute/path/to/completed-pr-body.md
```

Start the body from `.github/pull_request_template.md` and keep the `Scope`,
`Boundary`, `Verification`, and `Risk` headings. The publisher fails before
push when the worktree is dirty, the branch is `master`, the branch is behind
`origin/master`, or the body contract is incomplete. It never bypasses required
checks and never deletes branches, which keeps multi-worktree development safe.

## Local Checks

```bash
composer run test:all
composer run check:boundary
git diff --check
```

Boundary checks:

```bash
rg "/v1/runtime/workflows/runs|wp_insert_post|wp_update_post" --glob '*.php' --glob '!build/**' .
rg "workflow engine|approval truth|proposal truth|billing truth" docs README.md AGENTS.md
```

`workflow/task queue`, `scheduler truth`, and `workflow engine` may appear in
documentation only as forbidden responsibilities. `observability buffer`,
`Site Knowledge change buffer`, and their WordPress cron flush hooks are allowed
only as bounded Cloud delivery transport.

For packaged plugin releases, also follow
[`docs/wordpress-org-release-gate.md`](docs/wordpress-org-release-gate.md),
including the Cloud Base URL check for local development versus production. For
public Cloud onboarding, use
[`docs/public-cloud-onboarding-checklist.md`](docs/public-cloud-onboarding-checklist.md).
