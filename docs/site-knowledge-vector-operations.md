# Site Knowledge Vector Operations

Status: active.

Npcink Cloud Addon is the WordPress-side connector for Cloud Site Knowledge. It
does not own vector indexing or vector database lifecycle.

## Local Permission

The settings page actions that request content index work require
`manage_options`, a valid nonce, and verified Cloud settings. Editor-facing
Toolbox usage may consume search results, but it must not trigger refresh,
rebuild, delete, or collection lifecycle actions.

The `Enable Site Knowledge delivery` setting is local delivery consent only. It
controls whether this WordPress site sends future public content changes and
administrator start/rebuild requests to Cloud Site Knowledge. It is not a vector
collection lifecycle switch. The setting defaults to disabled: verifying or
reconnecting Cloud credentials alone never grants content-delivery consent.

## Allowed Addon Operations

- Listen for published `post` and `page` changes.
- Listen for approved comment changes attached to public posts or pages.
- Buffer affected public ids for bounded delivery durability.
- Read Cloud's optional `site_knowledge_maintenance.v1` projection and
  automatically schedule a full public-content resend when Cloud reports that
  the active embedding space requires it.
- Persist only a bounded local delivery cursor, submit one batch through the
  existing runtime worker, poll that run to success, and then submit the next
  batch. This cursor is not index lifecycle, queue, or scheduler truth.
- Use that same cursor for administrator start/rebuild requests so the admin
  request performs no Cloud batch loop. Reject overlapping full-index actions
  instead of replacing an active cursor.
- Reuse the existing reconciliation hook hourly to discover Cloud maintenance
  intent; no separate maintenance scheduler or push channel is introduced.
- Let a present administrator enable or disable local Site Knowledge delivery
  consent from the WordPress Site Knowledge tab.
- Send `site_knowledge_sync.v1` with `sync_mode=refresh` for ordinary public
  content delivery only when local delivery is enabled.
- Let a present administrator explicitly start indexing from bounded public
  WordPress content only when local delivery is enabled.
- Let a present administrator explicitly request `sync_mode=rebuild` after
  typing `REBUILD` only when local delivery is enabled; Cloud clears and
  rebuilds the index.
- Let a present administrator explicitly request `sync_mode=delete` after
  typing `DELETE`; Cloud deletes the site index and WordPress content remains
  unchanged.
- Forward known Toolbox Site Knowledge search, status, and refresh contracts
  through `POST /v1/runtime/execute`.
- Cache and show a short-lived, read-only article-usage summary from Cloud's
  `site_knowledge_status.v1` quota projection while local delivery is enabled.
  Cloud remains the only source of index counts, limits, and quota state.
- Compare at most the 1,000 most recently modified public post/page ids through
  `site_knowledge_status.v1`. Cloud returns matching indexed ids only;
  WordPress remains the source of titles, permalinks, and modification times.
- Require Cloud's normalized `indexed_post_ids_requested` count to match the
  local comparison manifest before treating an absent id as `not_indexed`.
  Missing or partial comparison evidence does not produce article rows.
- Show only the truthful per-article states `indexed` and `not_indexed`, default
  the operator list to missing articles, and disclose when older content falls
  outside the bounded comparison.
- Let a present administrator refresh one currently published post or page
  through the existing `site_knowledge_sync.v1` public-content transport. The
  action is nonce- and capability-protected and does not create a local index
  task, change WordPress content, or claim Cloud completion.
- Show shallow connector state, buffered public changes, last delivery, last
  error, next flush, last local index action, and a link to Cloud Site
  Knowledge detail.

Administrator and automatic full-index delivery use the same cursor and flush
hook. Each invocation performs at most one Cloud POST or run-status GET. Manual
start uses `refresh` for every batch; manual and automatic rebuild use
`rebuild` for the first batch and `refresh` thereafter. A Cloud run that remains
pending is subject to a finite polling window and the existing three-attempt
delivery stop.

Automatic maintenance uses the same local delivery consent. It never bypasses
the disabled state, never lets WordPress choose a model, dimension, metric,
vector store, or metering class, and never creates a WordPress write. The first
batch uses `rebuild`; later batches use `refresh`; Cloud supplies and validates
the opaque request id and owns final Zilliz publication. A platform operator
may expedite or retry this flow from Cloud Admin, but the site administrator
does not need to start a full sync manually.

The status projection returned by
`npcink_cloud_addon_site_knowledge_change_bridge_health()` is
`site_knowledge_change_bridge_status.v1`. Consumers should expose it as
`change_bridge` and prefer `buffer_count` for the bounded local delivery-buffer
depth. That count is not vector queue truth, index freshness truth, collection
lifecycle truth, or Cloud diagnostics truth.

The bridge health projection also carries `ownership` and `truth_boundaries`
maps that mirror the Cloud `site_knowledge_status.v1` and
`site_knowledge_sync.v1` owner contract. Local consumers may render those maps
as read-only boundary detail: Cloud owns index execution, index lifecycle,
freshness, vector storage, embedding execution, and diagnostics; Cloud Addon
owns the delivery bridge; the local WordPress host owns source content, local
approval, and final WordPress writes. These maps are not settings, not
collection lifecycle controls, and not a Cloud-to-WordPress write grant.

## Forbidden Addon Operations

The addon must reject or omit:

- collection create, update, migrate, or delete controls
- embedding provider settings
- Qdrant or other vector database endpoint settings
- embedding dimensions and collection names
- stale-index policy controls
- local vector stores
- local indexing queues or scheduler truth
- direct WordPress writes from Site Knowledge results

## Content Admission

Refresh payloads may contain only public site content:

- published posts and pages;
- bounded article documents using the Cloud contract fields `post_id`,
  `post_type`, `post_status`, `title`, `url`, `modified_gmt`, `excerpt`, and
  `content_excerpt`, plus bounded existing term-name metadata under
  `taxonomies.category` and `taxonomies.post_tag`;
- changes to approved comments may trigger a refresh of the public parent post,
  but comment bodies are not nested inside article documents;
- truncation and payload-limit metadata.

The bridge must not send the retired article aliases `status` or `body`.
Dedicated Cloud comment admission, when explicitly enabled by a future bounded
contract, must use a separate top-level `comments[]` shape rather than nesting
comment content inside `documents[]`.

Each Site Knowledge search or status action uses a fresh operation id. Ordinary
change delivery derives a stable key from the exact public payload, while each
full-index cursor derives one stable key per request and batch. This prevents
uncertain retries from duplicating Cloud work without replaying a later user
action as an older request.

Refresh payloads must not contain:

- drafts, private posts, password-protected posts, or trashed content;
- user emails, orders, memberships, form submissions, support tickets, or
  private CRM data;
- provider credentials, API keys, cookies, nonces, tokens, or signing fields;
- Core approval records, proposal records, or audit truth;
- final WordPress write instructions.

Cloud Site Knowledge remains the owner for embedding, vector storage,
collection lifecycle, freshness policy, re-indexing, rerank, quota, and deep
diagnostics.

Disabling local delivery stops future public content delivery and disables
start/rebuild requests. It does not delete existing Cloud index data. Delete site
index remains available as an explicit cleanup path after typing `DELETE`.
