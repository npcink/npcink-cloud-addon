# Cloud Runtime Client Contract

## Purpose

`Npcink_Cloud_Runtime_Client` is the server-side transport client for current Npcink Cloud runtime APIs.

It is responsible for request signing, Cloud error mapping, and a small set of stable runtime/read methods.

It also exposes a bounded observability transport for opt-in, verified,
metadata-only plugin monitoring. Observability transport is for Cloud dashboard
projection, not for approval, audit, workflow, billing, prompt, router, preset,
or WordPress write truth.

## Constructor

```php
new Npcink_Cloud_Runtime_Client(array $config = array())
```

If no config is provided, the client loads normalized addon settings.

Required config:

- `base_url`
- `site_id`
- `key_id`
- `secret`
- `timeout`

## Methods

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
pull_media_artifact(string $artifact_id, string $trace_id = '')
acknowledge_media_artifact_delivery(string $artifact_id, array $payload, string $trace_id = '', string $idempotency_key = '')
get_current_entitlement(string $trace_id = '')
send_observability_events(array $events, string $trace_id = '', string $idempotency_key = '')
send_agent_feedback_event(array $payload, string $trace_id = '', string $idempotency_key = '')
get_agent_feedback_summary(int $window_hours = 24, string $trace_id = '')
get_observability_summary(int $window_hours = 24, string $trace_id = '')
send_customer_journey_events(array $events, string $trace_id = '', string $idempotency_key = '')
get_customer_journey_summary(int $window_hours = 24, string $cohort_id = '', string $trace_id = '')
```

The low-level signed `request()` helper is private implementation detail. It must enforce the endpoint allowlist in this contract and must not be exposed as a generic public Cloud proxy.

Every runtime, liveness, artifact, and authorization-exchange request also
passes through the shared Addon outbound policy. Non-local targets require
HTTPS and public-only DNS results; redirects are disabled because signatures
bind the original request path; TLS verification stays enabled; JSON calls
require a JSON response media type. JSON runtime responses are limited to 1
MiB, authorization responses to 64 KiB, and raw artifact previews to 25 MiB.
Only exact loopback hosts may use the explicit local-development exception.
The Addon does not pin the pre-dispatch DNS answer to the eventual transport
connection. WordPress safe HTTP validation and HTTPS certificate verification
remain mandatory, but trusted Cloud DNS is still an operational requirement
and sub-second DNS rebinding is a residual risk rather than a solved guarantee.

For Cloud jobs that move local media bytes or downloadable artifacts, host code
should use the verified helper:

```php
npcink_cloud_addon_verified_runtime_client(): ?Npcink_Cloud_Runtime_Client
```

It returns `null` until the addon settings have passed Save and Verify.

## Endpoint Mapping

| Method | Endpoint |
| --- | --- |
| `probe_connectivity()` | `GET /health/live`, then signed `GET /v1/entitlements/current` |
| `manual_readiness_test()` | Reuses `probe_connectivity()` and returns bounded local result shape |
| `execute_runtime()` | `POST /v1/runtime/execute` |
| `upload_wordpress_ai_alt_text_source()` (internal attachment handoff only) | `POST /v1/runtime/media/uploads` |
| `execute_wordpress_ai_connector_runtime()` | `POST /v1/runtime/execute` |
| `execute_wordpress_ai_image_generation_runtime()` | `POST /v1/runtime/execute` |
| `execute_toolbox_image_generation_runtime()` | `POST /v1/runtime/execute` |
| `execute_toolbox_audio_generation_runtime()` | `POST /v1/runtime/execute` |
| `execute_toolbox_site_ops_cloud_analysis_runtime()` | `POST /v1/runtime/execute` |
| `execute_toolbox_web_search_runtime()` | `POST /v1/runtime/execute` |
| `execute_toolbox_image_source_runtime()` | `POST /v1/runtime/execute` |
| `npcink_cloud_addon_dispatch_site_knowledge_runtime()` | `POST /v1/runtime/execute` |
| `request_image_context_evidence()` | `POST /v1/runtime/execute` |
| `upload_media_artifact()` | `POST /v1/runtime/media/uploads` |
| `create_media_job()` | `POST /v1/runtime/media/jobs` |
| `get_run()` | `GET /v1/runs/{run_id}` |
| `get_run_result()` | `GET /v1/runs/{run_id}/result` |
| `get_recent_nightly_inspection_runs()` | `GET /v1/runs/nightly-inspection/recent` |
| `retry_run()` | `POST /v1/runs/{run_id}/retry` |
| `pull_media_artifact()` | `GET /v1/runtime/media/artifacts/{artifact_id}/download` |
| `acknowledge_media_artifact_delivery()` | `POST /v1/runtime/media/artifacts/{artifact_id}/delivery-ack` |
| `get_current_entitlement()` | `GET /v1/entitlements/current` |
| `send_observability_events()` | `POST /v1/observability/plugin-events` |
| `send_agent_feedback_event()` | `POST /v1/agent-feedback/events` |
| `get_agent_feedback_summary()` | `GET /v1/agent-feedback/summary` |
| `get_observability_summary()` | `GET /v1/observability/plugin-summary` |
| `send_customer_journey_events()` | `POST /v1/customer-journey/events` |
| `get_customer_journey_summary()` | `GET /v1/customer-journey/summary` |

## Diagnostics Surface

The Cloud Addon `Advanced and troubleshooting > Checks` section reuses the existing connection state,
`probe_connectivity()` result cache, entitlement summary, and Cloud Portal
links. Liveness and signed-read state share one compact connection row. It does not expose the private `request()` helper,
register a Developer diagnostics route, or add ad hoc Cloud service endpoints.

The bounded manual readiness result contract is
`cloud_addon_readiness_result.v1`. It may expose only non-secret fields:
`manual_test_action`, `connector_slot`, `connector_diagnostic_category`,
`credential_slot_readiness`, `signed_transport_status`,
`service_liveness_status`, `status`, `bounded_status`, `owner_label`,
`blocked_reason`, `next_action`, `next_safe_action`, `support_facts`,
`copyable_support_facts`, `diagnostic_panel_groups`,
`write_posture=read_only`, and `tested_at`.
`connector_diagnostic_category` is a bounded operator bucket derived from the
existing local slot, liveness, and signed-read statuses, such as
`not_configured`, `credential_missing`, `cloud_unavailable`,
`signed_transport_failed`, `ready`, or `unknown`.
The local Diagnostics page must trigger this test only through an explicit
administrator action and may display the latest bounded result; page render
must not automatically run `/health/live` or the signed entitlement read.
Support facts may include booleans such as whether credential slots are
present, the Cloud host, timeout, liveness status, signed-read status, and the
signed read endpoint. They must not include the stored secret, raw provider
logs, raw prompts, raw outputs, Authorization headers, cookies, nonces,
billing/quota detail, queues, registries, approval records, or WordPress write
targets.

`diagnostic_panel_groups` is an additive read-only projection over the same
manual result. Its fixed initial groups are `local_configuration`,
`cloud_connectivity`, `signed_transport`, `entitlement_readiness`, and
`support_facts`. Every group exposes `diagnostic_panel_group`,
`diagnostic_category`, `severity`, `owner_label`, `bounded_status`,
`blocked_reason`, `safe_support_facts`, `next_safe_action`,
`visibility=administrator_only`, and `write_posture=read_only`. It adds no
request, endpoint, option, queue, registry, durable history, or provider log.

Capability rows such as Platform Models, provider readiness, Cloud web search,
image source search, image generation, and Site Knowledge bridge must only show
status backed by an existing addon contract. If no addon read contract exists,
the row must say that the capability is not connected or Cloud-owned instead of
fabricating a check.

## Runtime Runs Ownership

The Cloud Addon does not expose a WordPress runtime-runs UI. Run history,
run-ID lookup, status, result, retention, and retry presentation belong to
Cloud, where technical detail can be shown without turning WordPress settings
into an operations console.

The existing `get_recent_nightly_inspection_runs()`, `get_run()`,
`get_run_result()`, and `retry_run()` methods remain bounded transport
contracts for maintained integrations. They must not create local run truth,
submit scheduled reviews, rebuild Toolbox local snapshots, create Core
proposals, approve changes, create a local retry queue, or write WordPress
data.

## Signing

## Entitlement Projection

`get_current_entitlement()` returns Cloud entitlement detail for local display.
When Cloud includes `entitlement.pro_cloud_runtime`, the addon summary preserves
that read-only detail for local plugins that need Pro Cloud Runtime status, such
as Nightly Site Inspection run quota, remaining runs, batch limits, result
retention, payload modes, and quota exhaustion.

The normalized projection includes `contract_reuse` so local displays can show
that Cloud owns runtime/detail, Toolbox owns product buttons, Core owns proposal
handoff, Adapter owns execution profiles, and Toolkit owns ability contracts.
The addon role is signed transport only; the projection must keep
`adds_registry`, `adds_scheduler_truth`, `adds_approval_store`, `adds_queue`,
and `adds_write_executor` false.

Runtime projection field types:

- `max_nightly_inspection_runs_per_period`, `used_nightly_inspection_runs`, and
  `remaining_nightly_inspection_runs` are numeric quota fields.
- `max_batch_items` and `result_retention_days` are optional numeric fields; if
  Cloud omits them, the addon must render them as unavailable rather than `0`.
- `quota_exhausted` is a boolean projection. False-like strings such as
  `"false"` must not render as exhausted.

This projection must not be treated as local billing truth, a local quota
engine, scheduler truth, queue state, retry ownership, or WordPress write
authority.

## Signing

Signed requests include:

- `X-Npcink-Site-Id`
- `X-Npcink-Key-Id`
- `X-Npcink-Timestamp`
- `X-Npcink-Signature`
- `X-Npcink-Trace-Id`
- `traceparent`
- `X-Npcink-Nonce` for POST requests
- `Idempotency-Key` when provided

The signature is HMAC SHA-256 over:

```text
METHOD
path
site_id
key_id
timestamp
nonce
idempotency_key
traceparent
sha256(body)
```

## Error Shape

The client returns a decoded Cloud response array on success.

On failure it returns `WP_Error` with:

- local error code prefixed with `cloud_`
- bounded, redacted human-readable message
- local non-success `status`
- upstream `cloud_http_status` as transport evidence
- bounded `cloud_error_code`

Raw Cloud failure payloads are not projected into `WP_Error`; untrusted nested
fields may contain provider diagnostics or credentials and are therefore not a
local public contract.

## Observability Transport

`send_observability_events()` may send only sanitized plugin behavior metadata
received from the Addon observability collector. Event payloads must be shaped
by an explicit allowlist before they reach the runtime client.

`get_observability_summary()` reads a Cloud-generated aggregate dashboard
projection. The local Addon may cache the summary for display only.

`send_agent_feedback_event()` may send only `cloud_agent_feedback.v1` local
operator feedback for Cloud eval and quality rollups. The payload must preserve
local approval, preflight, and final write truth; Cloud must not treat it as
training permission, workflow truth, proposal truth, or WordPress write
authority.

`get_agent_feedback_summary()` reads an aggregate eval/quality projection for
display. It is read-only and must not become local approval, proposal, workflow,
or WordPress write authority.

Observability transport must not send prompts, generated content, article body
content, media bytes, raw request payloads, raw response payloads, provider
credentials, Cloud API secrets, passwords, cookies, nonces, Authorization
headers, database names, table names, or filesystem paths.

The Addon-owned observability buffer is a temporary transport buffer only. It
must not be treated as Core audit truth, proposal truth, execution truth, AI
request logs, billing truth, or workflow/task queue truth.

## Image Context Evidence Transport

`request_image_context_evidence()` accepts only the Toolbox
`image_context_evidence_request.v1` artifact. It must keep:

- `write_posture=suggestion_only`
- `direct_wordpress_write=false`
- `no_local_model=true`
- `no_media_write=true`
- `source_policy=bounded_media_urls_for_visual_context_only` for URL-only
  requests, `bounded_source_artifacts_for_visual_context_only` for
  Artifact-only requests, or the bounded mixed-source value

The method dispatches a bounded `npcink-cloud/image-context-evidence` runtime
execute payload with `profile_id=vision.ai` and
`execution_kind=image_context_evidence`. It may send only a bounded media URL
or same-site short-TTL `source_artifact_id`, attachment id, MIME type,
title/filename context, and local candidate-quality flags. Artifact requests
use `data_classification=internal`; URL-only requests retain
`public_site_media_metadata`. It must not send provider credentials, Cloud
signing data, raw WordPress database fields, local filesystem paths, media
bytes, Data URLs, or Base64.

The method returns `image_context_evidence.v1` only as suggestion-only evidence
for local review. It must filter Cloud evidence to the requested attachment ids,
force `direct_wordpress_write=false`, require human visual confirmation, and
fail closed when Cloud returns no usable evidence. The bounded item projection
includes `visual_summary`, `subject_tags`, `visible_text`, `alt_text_basis`,
`caption_basis`, `confidence`, and `uncertainty_flags`; legacy `objects`,
`text_seen`, and `needs_human_visual_check` aliases remain additive for existing
consumers. Structured runtime source metadata is reduced to a non-secret
`evidence_basis` label plus `model_id`; provider and instance identifiers are
not projected.

This helper is not a local image recognition model, a product workflow, a
queue, a proposal creator, a media metadata writer, or a second control plane.
See `docs/image-context-evidence-integration-summary.md` for the cross-repo
Toolbox/Add-on rationale and boundary summary.

## WordPress AI Connector Runtime

The addon registers one fixed `Npcink Cloud` connector on the WordPress
Connectors surface when Cloud settings have passed Save and Verify. The
connector uses a synthetic marker option to satisfy the WordPress Connectors
page's `ai_provider` + `api_key` render contract and local AI plugin
availability checks. The marker is not a Cloud secret and is not exposed through
REST. Cloud credentials remain owned by the addon settings page, and the
Connectors card stays status-only for this fixed Cloud connector.

`execute_wordpress_ai_connector_runtime()` is the bounded transport seam for
text connector/provider calls. `execute_wordpress_ai_image_generation_runtime()`
is the bounded transport seam for the WordPress AI image generation feature.
Both must be treated as scene runtimes, not as generic chat providers, image
provider proxies, model proxies, or OpenAI-compatible endpoints.

`execute_toolbox_image_generation_runtime()` is the bounded transport seam for
Toolbox AI image candidate generation. It lets Toolbox keep the editor
recommendation UI and `image_candidate.v1` normalization while the addon owns
Cloud credentials, signing, runtime dispatch, and Cloud error mapping.

`execute_toolbox_audio_generation_runtime()` is the bounded transport seam for
Toolbox article audio candidate generation. It lets Toolbox keep the editor
audio candidate UX and Core-governed adoption planning while the addon owns
Cloud credentials, signing, runtime dispatch, and Cloud error mapping.

`execute_toolbox_site_ops_cloud_analysis_runtime()` is the bounded transport
seam for optional Toolbox Site Check Cloud detail. It lets Toolbox keep the
local Site Check product surface and `site_ops_cloud_analysis_result.v1`
normalization while the addon owns Cloud credentials, signing, runtime
dispatch, and Cloud error mapping.

`execute_toolbox_web_search_runtime()` is the bounded transport seam for
Toolbox managed web search evidence. It accepts `web_search.v1` request packets
and projects them to `channel=toolbox_web_search` for Cloud
`npcink-cloud/web-search`. The allowlist includes the exact-URL
`source_extraction_preview` intent and preserves its bounded `source_url` field;
the Addon remains a validating signed transport and does not fetch that URL.
It does not expose local provider keys, create
proposals, or write WordPress content.

`execute_toolbox_image_source_runtime()` is the bounded transport seam for
Toolbox image-source candidates. It accepts `image_source_cloud_request.v1`
request packets and projects them to `channel=toolbox_image_source` for Cloud
`npcink-toolbox/search-image-source`. It does not import media, set featured
images, write attribution, or own image-source candidate UX.

The optional PHP AI Client provider registers `npcink-cloud-scene-text`,
`npcink-cloud-scene-vision`, and `npcink-cloud-scene-image` as scene wrapper
models. These ids represent bounded WordPress AI surfaces, not bottom-level
Cloud provider model ids. The addon may make those wrappers first-choice
text/vision/image preferences only after Cloud settings pass Save and Verify;
otherwise it must preserve the WordPress AI plugin's original preferred model
order. Bottom-level provider/model routing stays with Cloud hosted runtime
profiles.

The addon registers a bounded `wpai_preferred_vision_models` override only for
WordPress AI alt-text generation. The scene must carry a local WordPress
`attachment_id` that the current user may edit. The provider wrapper verifies
that the attachment resolves to a regular readable file under the WordPress
uploads directory, limits the source to 8 MiB, and accepts only detected JPEG,
PNG, or WebP content whose MIME matches WordPress attachment metadata.

The attachment id is captured only from `wp_before_execute_ability`, after
WordPress Ability input validation and permission checks. The wrapper rejects
stack inspection and binds the checked path metadata to the opened file with
`stat()`/`fstat()` before reading. MIME is detected from the bounded bytes that
were actually read. The transport also requires current Save-and-Verify state
and rejects returned artifacts whose remaining lifetime cannot cover the
bounded execution window.

The wrapper then calls the dedicated
`upload_wordpress_ai_alt_text_source()` transport. That method sends one
short-TTL `media_upload_request.v1` image upload through
`POST /v1/runtime/media/uploads`, validates the returned same-site available
artifact against its media kind, MIME, byte size, checksum, identifier, and
expiry, and returns only the bounded artifact descriptor. It is not a generic
upload API or artifact registry. External image URLs, attachment URLs, Data
URLs, caller-supplied base64, arbitrary file paths, and compatibility fallback
shapes are rejected before Cloud execution.

The installed upstream WordPress AI alt-text ability currently converts its
local file reference to a Data URL before selecting an AI Client model. The
addon must not override or short-circuit that ability through
`wp_pre_execute_ability`, because that would make the addon responsible for
upstream validation, permission, and result semantics. Eliminating the
upstream transient base64 allocation therefore depends on a future public
attachment-reference model seam. This known upstream cost does not change the
Addon transport contract: no Data URL or base64 crosses the Cloud boundary.

Only after upload succeeds may `alt_text_suggest` execute. Its scene request
contains exactly `source_artifact_id`, `prompt`, and the optional bounded
`filename`, `title`, `existing_alt`, `existing_caption`, `locale`, and
`max_tokens` fields. Alt-text requests normally use
`data_classification=internal`; when their bounded text context contains an
obvious personal-data value, they use `data_classification=pii` with
`storage_mode=no_store`. They remain `suggestion_only`; the addon does not
become a generic vision provider or router, write media metadata, or own final
approval.

Input must use the platform-neutral
`contract_version=cloud_connector_runtime.v1` envelope and one of the supported
WordPress task surfaces:

- `title_generation`
- `excerpt_generation`
- `meta_description`
- `content_summary`
- `content_rewrite`
- `content_classification`
- `comment_moderation`
- `comment_reply_suggest`
- `alt_text_suggest`

The method projects accepted requests into a fixed runtime payload:

- verified top-level `site_id`
- `ability_name=npcink-cloud/connector-runtime`
- `channel=editor`
- `execution_kind=text` for text tasks or `vision` for `alt_text_suggest`
- `execution_pattern=inline`
- `data_classification=internal` with `storage_mode=result_only` for ordinary
  editor scenes
- `data_classification=pii`, `storage_mode=no_store`, and `retention_ttl=0`
  when the normalized scene contains an obvious email, phone-number, or
  national-id-like value
- `input.site_url=untrailingslashit(home_url('/'))`
- `input.platform_kind=wordpress`
- `input.connector_id=npcink-cloud-addon`
- `input.connector_version=<active addon version>`
- `input.suggestion_only=true`
- `input.operation_contract.contract_version=wordpress_operation.v1`

Provider fallback posture is owned by the selected Cloud profile/runtime. The
Addon does not send a local `policy.allow_fallback` override for this connector.
The personal-data check is a lightweight transport backstop aligned with the
Cloud runtime guard, not a general DLP classifier or a new local content policy
surface.

The nested operation contract has exactly `contract_version`, `task`, and
`request`. Platform identity stays in the connector envelope; WordPress task
semantics stay in `wordpress_operation.v1`. For `title_generation`,
`content_summary`, and `content_rewrite`, `request.source_text` is the actual
single AI Client user message. `request.system_instruction` is optional. Those
three tasks reject legacy `prompt`, `post_title`, and `post_excerpt` fields;
embedded content tags are transported as opaque text. Other bounded tasks such
as alt text may keep their task-specific prompt field.

The method rejects generic chat or provider-control fields such as `messages`,
`conversation_id`, `session_id`, `thread_id`, `tools`, `tool_calls`,
`functions`, `function_call`, `stream`, credentials, cookies, nonces, and signed
headers. It also bounds source/prompt/body size and clamps timeout to 60
seconds, retention, and retry values.

Text and alt-text consumers accept one result shape only:
`response.data.result.contract_version=cloud_connector_result.v1`,
`suggestion_only=true`, `connector_id=npcink-cloud-addon`, and an
`operation_contract` whose `contract_version=wordpress_operation.v1` and task
matches the current request. Only then is output read from
`response.data.result.output.output_text`. Legacy result layouts are not read.

For quality-accepted editor tasks, the scene request may carry the optional
bounded shape `site_knowledge_reference={enabled:boolean,mode:string}`. The
task-bound modes are currently `site_title_style` and `site_summary_style` for
title and summary respectively. Excerpt, meta description, classification, and
custom registered tasks continue through the ordinary Cloud runtime without
Site Knowledge generation reference. The
local `site_knowledge_generation_reference_enabled` permission is the only
preference truth and defaults to off. When enabled, the WordPress AI scene
wrapper adds the task-bound hint automatically; callers cannot provide source
texts, taxonomy terms, chunks, scores, URLs, or other reference payloads. Cloud
may use hidden Site Knowledge style or existing taxonomy history, while the
addon and WordPress AI plugin continue to receive only the ordinary task result.
Cloud may translate this hint into its internal `generation_context.v1` runtime
pack with task-specific relevance, dedupe, self-match exclusion,
reference-count, and character-budget policies. That internal pack is not a
caller field or an Addon-owned contract. The Addon must continue rejecting
caller-supplied source texts, chunks, scores, URLs, taxonomy terms, retrieval
limits, and context budgets, and must not expose relevance or reference detail
in the WordPress AI user interface.

Runtime support is quality-gated per task rather than implied by transport
acceptance. Current local evidence keeps generation reference limited to title
and summary. Other task families remain available without reference and may be
reconsidered only after task-specific quality evidence exists.

The explicit local A/B evaluator emits
`wp_ai_generation_reference_eval.v2`. Its default gate still requires at least
three published posts across all five supported tasks. Operators may set
`WP_AI_EVAL_MIN_POSTS=1` only for a quick post-change smoke; such a reduced run
is not promotion evidence. `WP_AI_EVAL_TASKS=title` may further narrow a smoke
to one or more comma-separated supported tasks. The eval-lab promotion gate
still requires at least 15 task pairs plus human validation.

Image generation input must use
`contract_version=image_generation_request.v1`, `task=image_generation`, and a
single text prompt. The addon projects accepted requests into Cloud's existing
`npcink-cloud/generate-image` runtime ability with
`execution_kind=image_generation`, `channel=wordpress_ai_connector`,
`storage_mode=result_only`, and `policy.allow_fallback=false`. The addon does
not choose provider keys or expose model routing; Cloud owns image-generation
provider/profile selection. The image generation seam rejects generic chat,
tool, stream, and credential fields, clamps image count to 1-4, clamps timeout
to 90 seconds, and does not support reference-image refinement.

Toolbox image generation input uses the same `image_generation_request.v1`
contract and `task=image_generation`, but the addon projects it as
`channel=toolbox_image_generation` with one allowlisted `source_surface` such
as `toolbox_featured_image`. It returns only the Cloud runtime response;
Toolbox must still normalize generated assets into `image_candidate.v1`, and
any media import or featured-image adoption remains with the local
Core/Adapter/Abilities path.

Toolbox audio generation input uses `contract_version=audio_generation_request.v1`
and one supported intent: `article_narration` or `article_audio_summary`. The
addon projects it as `channel=toolbox_audio_generation` with an allowlisted
`source_surface`, `storage_mode=result_only`, and `policy.allow_fallback=false`.
It returns only the Cloud runtime response; Toolbox must still normalize audio
candidates and any media import, post meta write, or playback adoption remains
with the Core/Adapter/Abilities path after operator review.

Toolbox Site Ops Cloud analysis input uses
`contract_version=site_ops_cloud_analysis_request.v1` and expected result
contract `site_ops_cloud_analysis_result.v1`. The addon projects it as
`channel=toolbox_site_ops_cloud_analysis`, `execution_pattern=whole_run_offload`,
`storage_mode=result_only`, and `policy.allow_fallback=false`. It rejects
generic chat/tool/stream/credential fields and requests that are not
`runtime_detail`, `suggestion_only`, and no-write. It returns only the Cloud
runtime response; Toolbox must still render it as review-only detail, and any
Core proposal or WordPress write remains outside this addon transport.

The optional PHP AI Client provider may call this helper only when the current
call stack originates from a known WordPress AI plugin Ability class and maps to
one of the supported task surfaces. Direct free-form `wp_ai_client_prompt()`
calls, chat history, tools, and web search are rejected before a Cloud request is
made.

For structured WordPress AI scenes, the addon sends only a shallow
`response_format` hint such as `json` or `text`. It must not forward the AI
Client's full `output_schema` into Cloud public runtime input; schema ownership
and response parsing remain with the WordPress AI ability surface.

## Boundary

The client must not add workflow repair, workflow/task queue operations,
scheduler truth, approval operations, billing mutation, prompt mutation, router
mutation, preset mutation, or WordPress write methods.

The private request helper must reject any signed path outside the endpoint
mapping above. In particular, it must reject workflow runtime, generic artifact,
support-bundle, generic file-upload, database-export, raw-payload, approval,
proposal, billing-mutation, prompt, router, preset, and WordPress write
endpoints. The sole upload exception is the named, image-only, short-TTL
`upload_wordpress_ai_alt_text_source()` method above; it must not be widened
into a caller-selected endpoint, media kind, retention policy, or registry.

## Media Derivative Transport

`Npcink_Cloud_Media_Derivative_Transport` is a bounded host-facing helper
for the local ability `npcink-abilities-toolkit/build-media-derivative-cloud-request`.

It may:

- validate the read-only ability output;
- reject payloads that include credentials, Authorization data, or signed
  headers;
- reject unverified Cloud credentials before dispatch;
- attach a host-supplied source upload or short TTL source artifact id;
- attach an optional host-supplied watermark upload or short TTL watermark
  artifact id when the ability response includes a watermark plan;
- upload bounded source bytes through `POST /v1/runtime/media/uploads` and
  dispatch the artifact-referenced job through `POST /v1/runtime/media/jobs`;
- pull one non-expired derivative artifact through
  `pull_media_artifact(string $artifact_id, string $trace_id = '')` and the
  explicit signed `GET /v1/runtime/media/artifacts/{artifact_id}/download`
  endpoint, verify its bytes and decoded image, and only then call
  `acknowledge_media_artifact_delivery()` through the independent
  `POST /v1/runtime/media/artifacts/{artifact_id}/delivery-ack` resource;
- convert a Cloud derivative artifact descriptor into a Core-ready local
  proposal payload.

The upload response must be the exact 11-field `media_upload_result.v1`
artifact: `artifact_id`, `media_kind`, `status`, `content_type`, `format`,
`width`, `height`, `filesize_bytes`, `checksum`, `expires_at`, and `purged_at`.
An upload accepted for a new media job must report `status=available` and
`purged_at=null`; the former exact 10-field shape, lifecycle aliases, and
additional fields fail closed.

It must not:

- call the ability itself;
- upload or download bytes through undocumented or generic Cloud endpoints;
- own a source media registry or logo registry;
- persist proposals;
- approve proposals;
- replace attachment main files;
- update `_wp_attachment_metadata`;
- perform any WordPress write.

The proposal payload generated by this helper always reports
`final_write_owner=local_wordpress_host` and `default_action=preview_only`.
Dispatch and run-status reads return the exact eight-field public projection
`run_id`, `status`, `job_type`, `created_at`, `updated_at`, `artifact`,
`warnings`, and `error`. Status projections always set `artifact=[]`, including
for `succeeded`, `failed`, and `canceled`; terminal error facts remain bounded
inside `error`. Only the separate run-result read may populate `artifact`.
The final Cloud result is accepted only as the exact
`media_derivative_result.v1` envelope with
`artifact_type`, `contract_version`, `workflow_metadata`, and `artifact`.
Its artifact is the exact 12-field Cloud descriptor: `artifact_id`,
`artifact_reference`, `expires_at`, `suggested_filename`, `filename_basis`,
`mime_type`, `format`, `width`, `height`, `filesize_bytes`, `checksum`, and
`processing_warnings`. Image facts are rejected when either axis exceeds 8192
pixels or total area exceeds 16777216 pixels. The Addon projects that once into the exact local
11-field proposal descriptor by removing `artifact_reference` and replacing
the prefixed checksum with lowercase bare `sha256`.

`npcink_cloud_addon_receive_media_derivative_artifact()` accepts only that
exact local 11-field descriptor and returns exactly `artifact_id`, `contents`,
`mime_type`, `width`, `height`, `filesize_bytes`, `sha256`, `expires_at`,
`transfer_evidence`, and `delivery_ack`. Every media POST and signed pull uses
a fresh nonce independent from trace and idempotency. The ACK uses its own
idempotency key, has scope `verified_transfer_only`, and must return
`artifact_expires_at` exactly equal to the reviewed local 11-field descriptor
expiry. It may neither shorten nor extend that expiry, including when the
preserved artifact lifetime exceeds six minutes after acknowledgement.
