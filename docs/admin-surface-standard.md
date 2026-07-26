# Cloud Addon Admin Surface Standard

Status: active for `Npcink AI -> Cloud Addon` when Toolbox is active, with
`Settings -> Npcink Cloud Addon` as the standalone fallback.

## Purpose

The Cloud Addon admin page is a thin connector surface. It opens the Cloud
Portal authorization flow by default, stores the returned Cloud Base URL and
Cloud API Key, verifies signed connectivity, and shows read-only connection,
entitlement and opt-in connector state.

## Default View

When not configured or not verified, the default page should prioritize:

- connection state and blocking reason;
- one primary action to add the current WordPress site in Npcink Cloud;
- the resolved Cloud base URL and current site URL as context only.

Cloud Base URL must use HTTPS except for exact local development hosts
(`localhost`, `127.0.0.1`, or `::1`) when WordPress explicitly reports the
local environment (or the local-development constant opts in). The environment default is
`http://localhost:8010/` for local WordPress environments and
`https://cloud.npc.ink/` otherwise. Manual Base URL and Cloud API Key wrapper
entry belong in `Connection Management > Manual fallback` as a recovery
fallback for local debugging or authorization outages.

The default `Connect` view may expose one folded `Advanced connection /
Self-hosted Cloud endpoint` entry. That entry only changes the authorization target for a
compatible Npcink Cloud deployment; it must not save partial credentials before
authorization completes and must not manage Cloud sites, keys, billing, models,
router, workflows, or runtime policy.
Cloud authorization entries should open in a new browser tab so the WordPress
admin page remains available while Cloud creates or activates the site
connection.
The connection action must state that Free service and credits belong to the
selected Cloud account rather than the site, and that cross-account changes are
subject to the removal and cooldown requirements shown by Cloud.

See `docs/connect-ui-and-zh-cn-localization-closeout-2026-07-09.md` for the
closeout rationale behind the folded endpoint entry, new-tab authorization
behavior, and zh_CN fixed-string maintenance boundary.

When verified, the default page should prioritize:

- `Overview` as the first working tab, showing plan/entitlement plus
  attention-only monitoring or Site Knowledge rows. It also contains the
  immediate-save local permissions; generation reference is dependent on Site
  Knowledge delivery and metadata-only monitoring consent stays folded under
  `More local permissions`;
- a dedicated Site Knowledge tab for local delivery consent, bounded public
  content refresh transport, explicit administrator delivery intents, and
  shallow bridge state while Cloud owns index execution, rebuild/delete
  handling, lifecycle, and freshness policy;
- one `Advanced and troubleshooting` entry for compact account/usage
  projections, local monitoring upload state, connection checks, runtime runs,
  and connection recovery;
- a clear path to update/re-verify settings.

Toolbox no longer owns Cloud Checks or Troubleshooting Checks for basic AI
connection, Hosted Runtime, Cloud search, Cloud image/source, quota,
entitlement, or service health. `Advanced and troubleshooting` is the local
entry for those Cloud connection and service-status details, but it must remain
a summary/detail surface rather than a Toolbox product workflow or operations
console.

## Tab Model

Verified admin navigation should stay at three top-level entries:

- `Overview`: compact plan plus attention-only connector rows, followed by the
  two primary local permission switches and folded secondary consent.
- `Site Knowledge`: delivery, buffered public changes, and last delivery plus
  the common refresh action. Errors appear only when present. Index operations
  use an explicit `Manage index` entry; local error or WP-Cron recovery facts
  appear under `Technical delivery details` only when action is needed.
- `Advanced and troubleshooting`: service detail, checks, runtime runs, and
  connection recovery as secondary tabs. Manual credentials stay inside a
  collapsed recovery disclosure.

Do not reintroduce separate `Status`, `Troubleshooting`, `Connection
Management`, `Details`, or `Runtime Runs` top-level tabs when the content fits
one of the three entries above. Old `details`, `status`, `diagnostics`, and
`runtime_runs` URLs may redirect to the current tab
owners for compatibility.

## Advanced / Low-Frequency Details

Low-frequency details may include:

- manual Cloud Base URL and Cloud API Key wrapper recovery entry;
- a local disconnect action that explicitly says it clears only WordPress-side
  credentials and buffers, does not release the Cloud site, and does not start
  the cross-account cooldown;
- a last connection failure and sanitized Cloud error classification, when present;
- compact package and availability fields plus one combined credit usage row;
- Cloud-owned runtime recent/status/result detail and retry request entry;
- sanitized diagnostics rows and Cloud detail links;
- metadata-only monitoring buffer/error status only when action is needed;
- last verification failure text.

These details should move into `Advanced and troubleshooting` or an explicit
collapsed technical-detail entry unless they are blocking the current task.
Do not surface internal enum fields such as credit policy or runtime local truth in the default admin UI;
keep those in tests and boundary documentation.
Do not copy Cloud observability aggregates, Agent quality breakdowns, contract
reuse matrices, worker phases, retryability flags, or Site Knowledge ownership
matrices into this local connector UI. Link to Cloud when that detail is useful.

## Layout and Copy Rules

Admin panels should use utility copy and summary/detail separation:

- keep one page title and one scope sentence;
- avoid repeated section titles when the active tab already names the area;
- place primary actions beside the relevant state, not scattered above and
  below the same section;
- place account-owned credit and Cloud-release consequences beside the connect,
  usage, and disconnect actions they qualify;
- prefer one table row per fact and avoid narrow label columns that force
  awkward wrapping;
- use table headers for tabular diagnostics;
- keep long explanatory text behind explicit detail affordances such as
  disclosure rows or a small `!` detail entry;
- avoid nested disclosure controls inside another disclosure;
- keep fixed admin terminology translated in zh_CN, while dynamic ability
  metadata, provider IDs, model IDs, slugs, and contract IDs remain owned by
  their source systems.

## Time Display

Cloud settings, entitlement summaries, and observability buffers may store UTC
or ISO timestamps for machine use, cache freshness, signing, upload status, or
Cloud correlation. Keep those stored values stable.

Any timestamp shown in the wp-admin settings page must be formatted through the
WordPress site timezone as `Y-m-d H:i:s`. Do not print raw UTC strings, ISO
timestamps, or Cloud machine timestamps directly in the human-facing admin UI
unless the label explicitly describes a machine/debug value.

## Do Not Add

Cloud Addon admin must not add:

- split credential editing fields for `site_id`, `key_id`, or `secret`;
- proposal, approval, preflight, audit, or WordPress write controls;
- router, prompt, preset, workflow/task queue, scheduler truth, or runtime repair
  control planes;
- Toolbox scheduled-review submission, local snapshot building, or Core handoff
  workspaces;
- billing truth, invoices, service operations console, or developer diagnostic
  routes.
- Tavily, Unsplash, provider-model selection, Cloud search execution, or image
  source search product tools.
- Site Knowledge execution truth, freshness policy controls, collection
  management, embedding/vector provider settings, or deep troubleshooting
  controls.

The admin page may expose a monitoring toggle, Site Knowledge local delivery
consent toggle, and attention-only local monitoring upload state. Cloud
observability and Agent feedback aggregates remain transport/data contracts and
Cloud-owned detail; wp-admin does not render or manually refresh those views.

Healthy verified connections do not repeat `Last verified`, entitlement sync
timestamps, or successful monitoring upload timestamps. Those values may
remain in machine contracts and explicit support checks, but default UI should
surface them only when they explain a blocker.

## Verification

Checks should confirm that the page never prints the stored secret, never
applies WordPress writes, and remains a connector/detail surface rather than a
second local or cloud control plane.
