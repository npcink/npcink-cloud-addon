# Cloud Addon Boundary

## Position

`npcink-cloud-addon` is a WordPress-side Cloud Connector / Cloud Addon.

It connects a local WordPress site to `npcink-cloud` and keeps only the local settings required to sign Cloud runtime requests.

It may also act as an opt-in observability transport for installed Npcink
plugins. Observability is metadata-only plugin monitoring for Cloud dashboards;
it is not local governance truth, Cloud governance truth, or a workflow/task
queue.

It may also bridge public WordPress content-change hints to Cloud Site Knowledge.
That bridge is delivery transport only; Cloud remains the Site Knowledge vector,
index, freshness, and collection lifecycle owner.

## Addon Owns

- Cloud Base URL.
- Cloud-side site authorization entry for the current WordPress site.
- Static, action-adjacent explanation that Free entitlement is account-owned
  and local disconnect neither releases the Cloud site nor starts its
  cross-account cooldown. Cloud remains the policy and timestamp authority.
- Connection Management manual fallback Cloud API Key wrapper entry.
- `mak1_{base64url(json)}` key parsing.
- Authenticated encrypted storage of the internal `site_id`, `key_id`, and
  `secret` envelope for server-side signing; split plaintext fields are never
  persisted in the WordPress option.
- HMAC signatures, trace headers, idempotency headers, and request nonce headers.
- Health and signed connectivity checks.
- Bounded manual connector readiness/test results with non-secret support facts.
- Table-native diagnostic grouping for existing local configuration,
  connectivity, signed transport, entitlement readiness, and support facts.
- Runtime request dispatch and run/result reads.
- Bounded Nightly Inspection runtime run detail: recent runs, one-run status,
  one-run result, and nonce-protected Cloud-owned retry requests.
- Verified dispatch helpers for host-owned media derivative Cloud jobs.
- Stats and entitlement read projections.
- Bounded Cloud checks for credentials, combined connection/signed-read state,
  hosted runtime availability, and an explicitly requested readiness result.
- Bounded Site Knowledge settings details for connector state, buffered public
  changes, last delivery, local delivery consent, manual public refresh
  transport, and explicit administrator delivery intents for Cloud-owned index
  operations.
- Opt-in, verified, metadata-only plugin observability upload.
- A bounded local observability buffer used only to survive temporary delivery
  failures before upload.
- A bounded Site Knowledge public content-change buffer used only to survive
  temporary delivery failures before Cloud refresh transport.
- A light `Npcink AI -> Cloud Addon` page when Toolbox is active, with a
  standalone `Settings -> Npcink Cloud Addon` fallback.

## Addon Does Not Own

- Approval truth.
- Proposal truth.
- Final WordPress writes.
- Workflow/task queue control.
- Scheduler truth.
- Workflow engine truth.
- Billing truth.
- Prompt control plane.
- Router control plane.
- Preset control plane.
- Cloud service operations console.
- Developer diagnostics routes.
- Site Knowledge index execution or lifecycle truth.
- Site Knowledge freshness policy, collection lifecycle, or deep
  troubleshooting ownership.
- Cloud search, image source search, or provider tool product UX.

## Local Persistence Boundary

Cloud Addon local persistence is limited to Cloud connection settings, bounded
status projections, and bounded delivery buffers. The observability buffer and
Site Knowledge change buffer are local delivery durability only: they help the
addon survive temporary upload failures and expose health counts, but they are
not queue truth, run truth, billing truth, indexing truth, approval truth, or audit truth.

The addon derives its credential-encryption key from the WordPress
authentication salt and fails closed when the credential envelope cannot be
authenticated or decrypted. Security salt rotation therefore requires the site
to reconnect. The envelope reduces plaintext exposure in database backups and
option inspection; it is not a defense against complete server compromise,
because code executing inside WordPress can access both the salts and runtime
credentials.

The addon must not create WordPress custom tables for workflow runs, Cloud
runtime history, provider request logs, Site Knowledge indexing jobs,
observability history, retry queues, leases, or dead-letter records. If a
feature needs long-term queryable history, reliable queue semantics, retry
recovery, usage/billing detail, entitlement detail, vector indexing lifecycle,
or diagnostics detail, that state belongs in Cloud service storage.

## Local Truth Rule

Local Core remains the owner for final WordPress permissions, proposal/preflight, approval, apply, and canonical WordPress writes.

Cloud may return generated output or write intent, but this addon must not apply it. Any write intent must be passed to Core proposal/preflight and final local approval paths.

For media derivative Cloud jobs, the addon may validate the local
`npcink-abilities-toolkit/build-media-derivative-cloud-request` output, sign the runtime
request, and build a Core-ready proposal payload from a non-expired Cloud
artifact descriptor. It may forward a host-supplied source upload or same-site
short TTL artifact id, and it may forward an optional host-supplied watermark
upload or artifact id when the local ability output contains a watermark plan.
Derivative proposal adoption requires a non-expired Cloud artifact id, and any
Cloud result artifact id, run id, or checksum must match the artifact descriptor
before the addon returns a local proposal payload. It must not call the ability,
persist the proposal, choose logo defaults, own a logo registry, replace the
attachment file, update attachment metadata, or perform adoption.

## Observability Transport Rule

Cloud Addon may collect and upload plugin behavior metadata only after Cloud
settings are verified and the administrator explicitly enables monitoring.

Allowed event fields are limited to operational metadata such as:

- `plugin_slug`
- `plugin_version`
- `source`
- `event_kind`
- `event_id`
- `emitted_at`
- `captured_at`
- `status`
- `status_detail`
- `error_code`
- `latency_ms`
- `ability_id`
- `proposal_id`
- `correlation_id`
- `adapter_request_id`
- `method`
- `route`
- `status_code`
- `mode`
- `deduplicated`
- `proposal_count`
- `blocked_count`
- `executed_count`
- `failed_count`

The local observability buffer is a bounded transport buffer only. It must not
be used as Core audit, proposal state, execution state, AI request logs, billing
truth, or local workflow/task queue state.

Monitoring must not collect or upload prompts, generated content, article body
content, media bytes, raw request payloads, raw response payloads, provider
credentials, Cloud API secrets, passwords, cookies, nonces, Authorization
headers, database names, table names, or filesystem paths.

Cloud observability summaries are read-only dashboard projections. They must
not drive local approval, proposal status, WordPress writes, router, prompt, or
preset configuration.

## Site Knowledge Change Bridge Rule

Cloud Addon may listen for public `post`/`page` and approved comment changes,
after Cloud settings are verified, buffer affected post ids locally, and request
a Cloud Site Knowledge refresh through `POST /v1/runtime/execute`.

Cloud Addon may also let a present administrator enable or disable local Site
Knowledge delivery consent, start indexing, request a rebuild, or request
deletion of the Cloud Site Knowledge index for this site. Those actions are
local operator intent plus bounded public WordPress manifests; Cloud remains the
executor and index lifecycle owner.

The local delivery consent setting controls future public content delivery and
administrator start/rebuild requests only. It is not index lifecycle truth.
It defaults to disabled and must be explicitly enabled by an administrator;
successful Cloud credential verification does not enable delivery.
Turning it off does not delete existing Cloud index data; the explicit delete
action remains available as a confirmed cleanup path.

The bridge must send only public content manifests, affected post ids, and
`write_posture=suggestion_only`. It must not create a local vector index,
perform re-index policy decisions, own stale-index detection, become a workflow
engine, become scheduler truth, or perform WordPress writes.

The settings page may expose a dedicated Site Knowledge tab, show delivery,
bounded buffered public changes, and the last local delivery outcome, update local delivery consent, and
send bounded public refresh plus explicit administrator delivery intents for
Cloud-owned index operations. Cloud remains responsible for index execution,
rebuild/delete handling, freshness policy, and lifecycle. The addon must not
expose collection management, stale-index policy controls, embedding/vector
provider settings, or Cloud operations-console actions.
Local error and WP-Cron recovery detail should appear only when action is needed.

The public PHP health seam
`npcink_cloud_addon_site_knowledge_change_bridge_health()` returns
`site_knowledge_change_bridge_status.v1`. Consumers should surface that payload
as `change_bridge` and use `buffer_count` as the preferred count field.
`buffer_count` describes only the bounded local delivery buffer that survives
until a signed Cloud refresh request can be sent. It is not queue truth,
freshness truth, index lifecycle truth, or Cloud diagnostics truth.

## Endpoint Rule

Allowed Cloud contract endpoints:

- `GET /health/live`
- `POST /v1/runtime/execute`
- `POST /v1/runtime/media/uploads` for bounded image source uploads only
- `POST /v1/runtime/media/jobs`
- `GET /v1/runs/{run_id}`
- `GET /v1/runs/{run_id}/result`
- `GET /v1/runs/nightly-inspection/recent`
- `POST /v1/runs/{run_id}/retry`
- `GET /v1/runtime/media/artifacts/{artifact_id}/download`
- `POST /v1/runtime/media/artifacts/{artifact_id}/delivery-ack`
- `GET /v1/entitlements/current`
- `POST /v1/observability/plugin-events`
- `POST /v1/agent-feedback/events`
- `GET /v1/observability/plugin-summary`
- `GET /v1/agent-feedback/summary`

Forbidden legacy endpoint:

- `/v1/runtime/workflows/runs`

Media derivative transport must use the named upload, job, signed pull, and
verified-transfer ACK endpoints,
run/result endpoints, and explicit derivative artifact download endpoint above.
The alt-text vision wrapper may use only the named media upload endpoint to
create one short-TTL image source artifact from an authorized local attachment,
then pass only its `source_artifact_id` to the existing execute endpoint. Do
not silently add ad hoc artifact upload, generic download, source registry, or
logo registry endpoints to the addon.

The wrapper may capture attachment input only after WordPress Ability
validation and permission checks. It must not replace or short-circuit the
upstream ability callback, inspect its call stack, or become a second Ability
validation/result owner merely to avoid upstream Data URL allocation. That
performance improvement requires an upstream attachment-reference seam.

Observability transport must use only the observability endpoints above. Do not
add ad hoc log upload, support bundle, file upload, database export, or raw
payload endpoints to the addon.

Site Knowledge change bridge transport must use the existing
`POST /v1/runtime/execute` endpoint only. Do not add local collection lifecycle
endpoints, generic indexing routes, or direct Cloud control-plane mutation paths
to the addon. Local Site Knowledge sync transport may carry
`sync_mode=refresh`, `sync_mode=rebuild`, or `sync_mode=delete` only as a
verified administrator intent; stale-index policy and collection lifecycle
operations stay in Cloud Site Knowledge.

Toolbox Site Knowledge runtime transport must use the existing
`POST /v1/runtime/execute` endpoint only. It may handle the bounded
`npcink_toolbox_site_knowledge_cloud_request` filter for the known search,
status, and sync contracts, but must keep every payload
`write_posture=suggestion_only`. It must not add local index jobs, stale-index
policy ownership, workflow queues, approval records, proposal records, or
WordPress writes.

Image context evidence transport must use the existing
`POST /v1/runtime/execute` endpoint only. It may forward a bounded
`image_context_evidence_request.v1` artifact for Cloud-owned visual recognition
and normalize `image_context_evidence.v1` as suggestion-only evidence. It must
not add a local image recognition model, local queue, proposal creation,
approval, media metadata write path, or generic image upload/download endpoint.
For the operator-only Local media pilot, the evidence seam may first use the
existing allowlisted `POST /v1/runtime/media/uploads` resource to create
same-site short-TTL image artifacts, then send only their ids to runtime
execute. It must not expose local paths or bytes in the execute payload, create
an artifact registry, or widen the endpoint allowlist.

Agent feedback transport must use only `POST /v1/agent-feedback/events` for
`cloud_agent_feedback.v1` local operator feedback and
`GET /v1/agent-feedback/summary` for read-only eval rollups. It is eval/quality
metadata, not approval truth, proposal truth, workflow truth, or WordPress write
authority.

Nightly Inspection runtime run detail may use `GET /v1/runs/nightly-inspection/recent`,
`GET /v1/runs/{run_id}`, `GET /v1/runs/{run_id}/result`, and
`POST /v1/runs/{run_id}/retry` only. It is a Cloud run-state/detail surface.
It must not submit scheduled reviews, reconstruct Toolbox snapshots, own a
local retry queue, create Core proposals, approve changes, or write WordPress
data.

## UI Rule

The local UI stays shallow:

- status
- diagnostics
- access settings
- validation feedback
- last verification time
- entitlement summary
- opt-in monitoring consent and attention-only local buffer/error status; Cloud observability and Agent feedback aggregates stay in Cloud
- a bounded `Advanced and troubleshooting > Runtime runs` section with compact Nightly Inspection availability/retention plus recent/status/result and retry request detail

The advanced checks table stays compact and may show credentials, one combined
connection/signed-read row, hosted-runtime, and an explicitly requested readiness result. It
must not add live checks on page render, durable diagnostic history, raw request
or response capture, or another service-status truth source.

It must not become a second control plane.

Toolbox no longer owns basic Cloud Checks / Troubleshooting Checks. Cloud
Addon may show Cloud connection and service-status diagnostics, but it must not
1:1 recreate the old Toolbox page or move Toolbox product workflows into this
settings surface. Missing Cloud service contracts must be shown as not
connected or Cloud-owned rather than simulated locally.
