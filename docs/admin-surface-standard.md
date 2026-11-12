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
The primary connection and inactive-site recovery actions use a same-tab,
click-time authorization form. This supplies fresh return context so Cloud
confirmation returns to WordPress for credential exchange and verification.
The folded custom-endpoint entry may open in a new browser tab. Cloud owns explicit
activation and any customer-selected site replacement; the Addon owns only
the handoff and verification. The separate activation recheck stays available
for older Cloud services and manual Portal lifecycle changes.
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

- `Overview`: period usage, folded capacity detail, embedded
  Site Knowledge status and its feature switch in one section, and folded privacy consent.
  Entitlement keeps the credit reset date visible and folds the full AI credit period; remaining limits fold
  under Credit and capacity details together with Cloud-reported credit balance, knowledge document
  capacity, and image capacity. Technical concurrency and batch limits belong in Cloud. Do not show remaining-capacity progress bars.
  Routine Site Knowledge delivery buffering remains silent;
  recorded delivery failures, delayed automatic updates and current Cloud capacity
  warnings create attention rows. Healthy Site Knowledge
  has no manual refresh action; recovery update appears only after a delivery
  failure. Local error or WP-Cron facts remain on Checks.
- `Checks`: stored connection verification and its date precede a recheck action;
  Troubleshooting details shows a short configuration summary, exceptions and the
  detailed-check action. Its closed Technical details disclosure owns connection,
  capability, signed-read, readiness and monitoring facts. Real failures open the
  parent and show their impact before technical evidence.
  Expired Cloud evidence must ask for another check rather than claim current health.
- `Connection`: verified connection summary, a site-scoped Cloud account entry,
  folded account change/disconnect, and one Advanced connection disclosure for support.

Do not reintroduce separate `Status`, `Troubleshooting`, `Advanced and
troubleshooting`, `Details`, `Site Knowledge`, or `Runtime Runs` top-level tabs
when the content fits one of the three entries above. Old `advanced&view=`,
`details`, `status`, `site_knowledge`, `diagnostics`, and `runtime_runs` URLs
normalize onto Overview / Checks / Connection for compatibility, but they must not render a local run surface.

## Overview Focus (2026-11-04)

- Healthy connection is a compact row, without success-card chrome. An existing
  connection warning replaces that row; do not also claim that AI is ready.
- The primary usage figure is Cloud-reported **账号本期已用（积分）**. Preserve zero;
  hide absent usage rather than deriving it from balance. The figure refreshes
  with the existing authenticated entitlement read.
- The used figure and its label share one line, followed by a quiet credit reset
  date. The full cycle, account ownership, capacity and operational limits share
  one closed-by-default Credit and capacity details disclosure, rendered as aligned name/value
  rows rather than a metric grid.
- Knowledge title and its immediate-save feature switch share one stable header
  outside the asynchronously replaced status summary. Description, quiet delivery
  status/time and contextual actions follow below.
  A quiet Manage excluded content link opens a nested content-selection page;
  do not embed a content list on Overview. One status badge describes progress; do not add a disabled
  “Syncing” button beside “Updating”. Only a recorded failure exposes recovery.
- Label the last local delivery as content delivery, not Cloud indexing
  completion. Routine pending counts stay out of the default view.
- Privacy consent stays folded, with the current anonymous-diagnostics choice
  in the summary. After changing it, reopen that disclosure and retain inline
  save feedback. The ask-once consent lifecycle is unchanged.
- Overview links upload failures to Checks. Checks and Connection own their
  contextual status; do not repeat Overview's global warning above their tabs.
- All three tabs share the same content-column edge. Keep Cloud lifecycle and
  technical execution detail in contextual Cloud links.

## Checks And Connection Focus (2026-11-04)

- Match Overview's flat, compact column; healthy states have no success-card chrome.
- Checks labels successful stored verification as historical and places its actual
  connection-verification time before a secondary recheck action. Do not claim a
  complete live health check from credential verification alone.
- Troubleshooting details owns support evidence and anonymous diagnostics delivery;
  genuine failures open the parent and show diagnostics failures inline. One
  closed Technical details disclosure separates support-only evidence from the
  beginner action; no further disclosure depth is allowed. Try AI features is a distinct,
  explicit disclosure, retaining the existing pause and capability rules.
- Connection shows verification context without mixing in plan labels. Current
  transport has no account name/email projection; use a site-scoped Cloud entry
  for account verification rather than inventing an identity or adding API fields.
- Account change and local disconnect share a closed-by-default Connection
  management disclosure. Keep consequences beside each revealed action, preserve
  JS/no-JS confirmation and nonce checks, and distinguish disconnect in red.
- Rechecking returns to its originating Checks or Connection tab; only those
  two local return destinations are accepted.
- Connection management and Advanced connection are the two low-frequency disclosures.
  No new options, transport calls, or control-plane ownership are introduced.

## Content Exclusions (2026-11-04)

Keep this low-frequency choice below Overview, without adding a menu or a
fourth top-level tab. The nested page has Search content and Excluded views.
Search runs only after a name, #ID, category or tag is submitted. Names match
titles only; #ID is an exact positive WordPress identity, with no numeric-title
ambiguity. Category and tag filters intersect and apply to posts only; they are
local search filters, never persistent exclusion rules. Keep filters folded
until requested and open while active, with a clear-filters link. Search queries
WordPress content, not a preloaded list of the latest 100 items. Both views paginate in bounded
pages. Both the entry and nested tab count confirmed exclusions separately from
pending removal/restoration;
do not claim Cloud completion before evidence returns. Missing or non-public
sources remain visible in Excluded but cannot be restored. Reuse the existing
scoped, nonce-protected exclude/restore handler. Exclusion uses one short
count-based confirmation, with a required confirmation checkbox when JavaScript
is unavailable; restoration needs no duplicate confirmation. Never require an
English confirmation word for these reversible source choices. Return notices
to this page with the name/ID, taxonomy filters and page context. No render-time Cloud HTTP, new delivery state,
or index lifecycle controls are introduced.

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
- avoid nested disclosure controls inside another disclosure, except the single support-only Technical details
  disclosure under Troubleshooting details; keep actionable failures outside it;
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

## Beginner Flow (2026-11-04)

- After verification, the header describes status, usage and knowledge settings.
  When the official AI settings page is registered and the administrator can edit
  posts, offer the post editor as the next step. It is primary only on the first
  healthy connection; later it is a quiet link. Respect unavailable host UI.
- Checks uses Check connection, Run detailed checks and Try AI features as
  distinct actions. Detailed checks/tests stay folded and tests disclose credit use.
  Show connection/capability facts before support-copy actions. Paused nightly
  inspection is omitted; its lifecycle remains Cloud-owned.
- Usage this period leads with credits used. Credit and capacity details folds
  balance, knowledge/image capacity and cycle dates, with a Cloud plan link.
  Do not repeat technical concurrency/batch limits locally.
- Failed anonymous diagnostic uploads identify that they do not block generation.
  Knowledge delivery failures explain that new content may be missing from AI
  references; technical error codes remain in Checks. Automatic updating says
  no action is needed without inventing progress or completion estimates.
- Switches disclose automatic saving only when JavaScript enables that behavior;
  no-JS retains Save. Success/error feedback stays beside the control and a
  failed write re-renders the stored value. Privacy explains its metadata-only
  scope and that opting out does not disable AI.
- The knowledge exclusion entry distinguishes confirmed excluded items from
  pending exclusion/restore intents. Search explains the effect on Cloud knowledge
  and preservation of WordPress content. Filters remain closed unless active.
- Connection has only Connection management and Advanced connection disclosures;
  the latter contains support guidance, ownership context and the existing manual
  form. Disconnect remains last in management with its confirmation safeguards.

## Expanded-State Acceptance (2026-11-04)

Check default and expanded states independently in all three tabs. Passing tests,
no overflow and closed-by-default controls do not prove readable hierarchy.
Inspect the actual screenshots for repeated status claims, uniform label/value
columns, technical codes drawing attention, action ordering and quiet support-copy
controls. Check at 1440×900, 782×900 and 480×900, including long errors and URLs.

Troubleshooting reads: configuration summary or exceptions, Run detailed checks,
Cloud detail link, closed Technical details, Copy troubleshooting information.
Configured capabilities are configuration evidence only, not completed generation.
Routine monitoring buffers are technical facts, never an error count. Copying
includes closed technical evidence and restores disclosure state afterward.

## Visible Plan Identity (2026-11-04)

Current plan is basic account context and stays beside Usage this period.
Do not hide its summary after a successful asynchronous read. Detailed credits,
capacities and cycle dates remain folded; unavailable reads retain their existing
message/retry path instead of inventing a tier.

## Important Information Visibility (2026-11-04)

The main usage figure states its Cloud account scope. Current plan and credit
reset date remain visible; detailed capacity and cycle facts may stay folded.

Confirmed unavailable AI capabilities appear outside closed support details;
Checks opens the affected information. Unknown or expired capability evidence
is not a confirmed outage. Cloud-reported near-full or full Site Knowledge
capacity is visible before documents are skipped and links to Cloud capacity
management rather than offering a local resend as a quota fix.

An overdue automatic update must have a visible Checks destination matching
the Overview link. Zero credits say used up, separately from a low balance.
The same cached signed read refreshes balance warnings, reset date, cycle and
image capacity; failed reads retain context with an explicit last-data label.
A first failed read without retained figures must not claim to show cached data.
No additional Cloud read, settings, lifecycle control or billing ownership is
introduced by these projections. Verify default disclosure ancestors and
state transitions, not only whether text exists in rendered HTML.

The historical problem inventory, rationale, evidence limits and dated Git
closeout are recorded in [the 2026-11-04 admin review](admin-focus-review-2026-11-04.md).

## Inactive-site recovery (2026-11-12)

A detailed signed read reporting `auth.site_inactive` must lead directly to the
existing same-tab authorization form, including when stored credentials remain
verified. A Connection link alone is insufficient if that page has no recovery
action. Successful verification clears the current administrator’s old manual
check transient; connection evidence never claims model generation. See the
[dated acceptance and limits](inactive-site-connection-acceptance-2026-11-12.md).
