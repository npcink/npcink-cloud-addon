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
  attention-only monitoring rows, embedded Site Knowledge status (sync /
  recovery actions), and the single immediate-save `Enable Site Knowledge`
  feature choice. Public-content delivery and supported generation reference
  move together; connector state is not presented as another permission.
  Metadata-only monitoring consent stays folded under `Privacy settings`;
- top-level `Checks` and `Connection` entries for connection checks and
  connection management; compact entitlement / monitoring detail stays on
  Overview;
- a clear path to update/re-verify settings.

Toolbox no longer owns Cloud Checks or Troubleshooting Checks for basic AI
connection, Hosted Runtime, Cloud search, Cloud image/source, quota,
entitlement, or service health. Local `Checks` and Overview entitlement detail
cover those Cloud connection and service-status summaries, but must remain
summary/detail surfaces rather than a Toolbox product workflow or operations
console.

## Tab Model

Verified admin navigation should stay at three top-level entries:

- `Overview`: usage metrics, compact entitlement / monitoring detail, embedded
  Site Knowledge status (visible Knowledge base title, sync / configure /
  recovery), one Site Knowledge feature switch, and folded privacy consent.
  Entitlement keeps Renews and AI credit period visible; remaining limits fold
  under More limits. Routine Site Knowledge delivery buffering remains silent;
  only a recorded delivery error creates an attention row. Healthy Site Knowledge
  has no manual refresh action; recovery update appears only after a delivery
  failure. Local error or WP-Cron facts remain on Checks.
- `Checks`: Run checks hint and Run readiness test share one heading row;
  Credentials and Cloud connection rows stay visible; Hosted Runtime, capability
  rows, and readiness history fold under Check details.
- `Connection`: account change, disconnect, and collapsed manual recovery.

Do not reintroduce separate `Status`, `Troubleshooting`, `Advanced and
troubleshooting`, `Details`, `Site Knowledge`, or `Runtime Runs` top-level tabs
when the content fits one of the three entries above. Old `advanced&view=`,
`details`, `status`, `site_knowledge`, `diagnostics`, and `runtime_runs` URLs
normalize onto Overview / Checks / Connection for compatibility, but they must not render a local run surface.

## Advanced / Low-Frequency Details

Low-frequency details may include:

- manual Cloud Base URL and Cloud API Key wrapper recovery entry;
- a local disconnect action that explicitly says it clears only WordPress-side
  credentials and buffers, does not release the Cloud site, and does not start
  the cross-account cooldown;
- a last connection failure and sanitized Cloud error classification, when present;
- compact package and availability fields plus one combined credit usage row;
- links to Cloud-owned technical detail when a local abnormal status needs investigation;
- sanitized diagnostics rows and Cloud detail links;
- metadata-only monitoring buffer/error status only when action is needed;
- last verification failure text.

These details should move into Overview entitlement, `Checks`, `Connection`,
or an explicit collapsed technical-detail entry unless they are blocking the
current task.
Do not surface internal enum fields such as credit policy or runtime local truth in the default admin UI;
keep those in tests and boundary documentation.
Do not copy Cloud observability aggregates, Agent quality breakdowns, contract
reuse matrices, worker phases, retryability flags, or Site Knowledge ownership
matrices into this local connector UI. Link to Cloud when that detail is useful.

## Status Card Pattern (2026-10-21)

Every admin state card (Overview connection row, Checks status, Connection
account/disconnect, first-connection onboarding) follows one shape:

- **One status sentence** that names the current state in plain language for an
  ordinary site owner.
- **One primary action** that is the next useful step for that state.
- **Rare or destructive actions stay secondary** (text / secondary button, or
  folded under More information). Do not compete with the primary action by
  placing a second primary button on the same card.
- Supporting description stays short; long diagnostics fold behind an explicit
  disclosure.

Healthy verified connections stay quiet: no extra success banners, no synonym
health labels next to "Connected".

## Overview Usage Copy (2026-10-21)

Overview may show period credit usage as a fact only:

- Prefer a single used figure such as **本期已用** (credits used this period).
- **Never** invent or display estimates such as 「约可再生成 X 篇」or any
  "about N more articles / images" extrapolation from remaining credits.
- Remaining balance, if shown at all, is a Cloud-reported number — not a
  generation forecast. Top-up / billing detail belongs in Cloud Portal.

## Consent (ask once)

Metadata-only monitoring and Site Knowledge delivery consent are asked once.
After the administrator answers (accept or decline):

- Do **not** re-prompt on reconnect, re-verify, or change-account / account swap.
- Change-account clears local credentials and buffers but must preserve the
  recorded consent answer so the popup does not return for the same site.
- Re-prompt only when consent was never recorded, or when a future product
  change explicitly resets that stored answer.

## Quota and Provider Error Codes (2026-10-21)

Editor and connector failures that carry a stable Cloud error code must keep
the dotted code intact (`commercial.quota_exceeded`, `provider.quota_exhausted`).
Persistence and display use `sanitize_error_code` (keep `.`); do not run those
codes through `sanitize_key`, which strips dots and breaks matching.

Mapping for site-owner copy:

| Code family | Meaning | User-facing direction |
| --- | --- | --- |
| `commercial.quota_exceeded` | This Cloud account's credits / quota are used up | Point to top-up; billing URL is Cloud `base_url` + `/portal/billing` |
| `provider.*` (e.g. `provider.quota_exhausted`) | Upstream / provider capacity or outage | Treat as **service unavailable** — do **not** say 「去充值」or send the owner to billing |

Keep the friendly sentence first and the stable code in parentheses. Full
history of the earlier mapping pass lives in
`docs/user-facing-error-surfacing-closeout-2026-10-02.md` (see the 2026-10-21
addendum).

## Self-check Before Handoff

Before asking the user to review an admin UI change:

1. Open the local admin preview (or equivalent) for the touched surfaces.
2. Verify **layout and typography**: spacing, hierarchy, orphan labels, mixed
   quote styles, and competing primary buttons.
3. Capture screenshots when the change is visual; fix layout issues yourself
   before handoff.
4. Prefer restrained UI for ordinary site owners — rare actions must not look
   like the main next step.

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

The page-level connection summary is reserved for unverified or unhealthy
states. A healthy verified connection appears only as the first compact row in
`Overview`; credential success and cached signed-read explanations stay out of
the default UI.

## Verification

### Refresh and recovery feedback (2026-10-13)

- Refresh counts, warnings, service labels and recovery actions from the same
  escaped server projection. A successful signed read may clear an obsolete
  connection warning; it must preserve independent local delivery failures,
  including failures recorded only as an error code.
- Never treat missing response state or expired cached evidence as current
  service or indexing success. Retain the most recent valid value on a later
  read failure, disclose the failure and offer retry only while it exists.
- Describe accepted recovery as scheduled or updating. Describe an empty
  delivery as no content sent. Clear historical dropped-content attention only
  after the existing full public-content delivery completes; do not erase
  persisted facts to make the interface appear healthy.
- Require an explicit accepted count before removing uploaded monitoring or
  customer-journey events from local buffers. An HTTP success envelope alone
  does not prove Cloud accepted the metadata.
- Verify fixed zh_CN text with a lagging site language pack as well as the
  bundled PO/MO. Existing translated text wins; bundled fallback remains
  addon-domain scoped and must not translate dynamic ability metadata.

Regression and integration evidence for these rules is recorded in
[the admin feedback audit](admin-feedback-audit-2026-10-13.md) and
[the Site Knowledge recovery follow-up](site-knowledge-recovery-closeout-2026-10-13.md).

Checks should confirm that the page never prints the stored secret, never
applies WordPress writes, and remains a connector/detail surface rather than a
second local or cloud control plane.
