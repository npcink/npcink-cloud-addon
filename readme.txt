=== Npcink Cloud Addon ===
Contributors: muze233
Tags: magick ai, cloud, hosted runtime
Requires at least: 7.0
Tested up to: 7.1
Requires PHP: 8.0
Stable tag: 0.2.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Thin Cloud connector for Npcink hosted runtime access, signing, health checks, entitlement summaries, and opt-in metadata monitoring.

== Description ==

Npcink Cloud Addon connects a local WordPress site to `npcink-cloud`.

It stores the Cloud Base URL and Cloud API Key, parses Cloud-issued keys, signs runtime requests, probes health, reads Cloud entitlement summaries, and can upload metadata-only plugin behavior events after explicit administrator opt-in.

It does not execute WordPress writes, approve proposals, own billing truth, or manage prompts, routers, presets, workflow/task queues, scheduler truth, or workflow engines. Its observability buffer is only a bounded delivery buffer for monitoring metadata and is not Core audit, proposal, execution, billing, or workflow truth.

For media derivative jobs, local host code may pass the read-only
`npcink-abilities-toolkit/build-media-derivative-cloud-request` ability output and a short TTL
source artifact descriptor to the addon for signed Cloud dispatch. The addon
requires verified Cloud settings, rejects credential-bearing ability payloads,
and returns proposal-ready data with `final_write_owner=local_wordpress_host`.
Final review, recording, replacement, rollback, and WordPress writes remain in
the local host/Core approval path.

For plugin monitoring, the addon may upload operational metadata such as plugin
slug/version, event kind, status, timing, error code, route, proposal id,
ability id, correlation id, and counters. It must not upload prompts,
generated content, article body content, media bytes, raw request or response
payloads, provider credentials, Cloud API secrets, passwords, cookies, nonces,
Authorization headers, database names, table names, or filesystem paths.
Customer-journey monitoring is limited to supported editor steps such as
generation started, succeeded, failed, retried, accepted, saved, or abandoned.
It does not send raw WordPress user or post IDs, email addresses, URLs, DOM
data, or free-form error messages. Uploads are authenticated to the configured
Cloud site, so they may be associated with that site; they are not used for
advertising, individual user scoring, or automatic WordPress content changes.
During a bounded invited-user observation window, the site operator may add one
opaque cohort identifier so Cloud summaries can exclude earlier technical test
events. The identifier must not encode an editor, account, site, article,
prompt, or content identity.

== External Services ==

This plugin connects to the Npcink Cloud service configured by the site administrator through the Cloud Base URL setting.

The plugin contacts the configured Cloud service after Portal authorization and connection verification, or when an administrator uses the advanced recovery form with a Cloud Base URL and API key. A local Npcink component may also explicitly use the Cloud runtime client.

Requests may include the configured site identifier, key identifier, request timestamp, nonce, trace identifier, idempotency key, HMAC signature headers, and read-only requests for health, run status, run result, usage statistics, entitlement summaries, observability summaries, and Agent feedback quality summaries. WordPress AI/runtime requests can include the prompt, source text, and task configuration supplied for that operation. Optional metadata-only monitoring is separate and does not include prompt or content data. The stored Cloud API Key secret is used server-side for request signing and is not printed in wp-admin.

For host-supplied media derivative runtime jobs, requests may also include a
short TTL source artifact descriptor and derivative request parameters from the
local read-only ability output. Cloud credentials and signed headers are added
by the addon transport and are not copied into the ability payload.

For Site Knowledge transport, requests may include public post/page identifiers,
public article/page content and public metadata when delivery is enabled, approved
comment change hints, local delivery consent state, and explicit administrator
delivery intents for Cloud-owned index operations. Drafts, private posts,
password-protected posts, credentials, and raw database fields must not be sent.

The Site Knowledge change bridge health seam returns
`site_knowledge_change_bridge_status.v1`. Local consumers should surface it as
`change_bridge` and treat `buffer_count` as bounded delivery-buffer depth only,
not queue truth, freshness truth, index lifecycle truth, or Cloud diagnostics
truth.

For WordPress AI connector scene runtime, image context evidence, Runtime Runs
detail, artifact preview download, and Agent feedback event transport, requests
may include the bounded scene request, media URL or artifact descriptor, run id,
artifact id, or local operator feedback metadata needed for that specific Cloud
runtime/read endpoint. The addon does not create Core proposals, approve
changes, execute WordPress writes, own retry queues, or own Site Knowledge index
lifecycle.

The configured Cloud service is responsible for its own privacy policy, terms of service, data retention, and account/key issuance. Because the Cloud Base URL is administrator-configured, site administrators should only connect this plugin to a Cloud service whose terms of service, privacy policy, data retention policy, and account/key issuance process they have reviewed.

Npcink Cloud service information:

* Terms of Service: https://cloud.npc.ink/terms/en/terms.html
* Privacy Policy: https://cloud.npc.ink/terms/en/privacy.html
* Data Retention: https://cloud.npc.ink/terms/en/data-retention.html

== Installation ==

1. Place this directory in `wp-content/plugins/npcink-cloud-addon`.
2. Activate `Npcink Cloud Addon`.
3. Open `Npcink AI > Cloud Addon`, or `Settings > Npcink Cloud Addon` when the addon is installed standalone.
4. Choose `Connect with Npcink Cloud` and authorize this site in the Portal.
5. If Portal authorization is unavailable, expand advanced recovery and enter the Cloud Base URL and API key, then choose `Save and Verify`.

== Frequently Asked Questions ==

= Does this plugin create Cloud API Keys? =

No. Keys are issued by Npcink Cloud.

= Do I need a Npcink Cloud account? =

Yes. A site administrator needs a Npcink Cloud account and Portal authorization. The advanced recovery form accepts a Cloud Base URL and API key when Portal authorization is unavailable.

= Does this plugin display the Cloud secret? =

No. The secret is stored for server-side signing only and is never printed on the settings page.

= When does the plugin contact Npcink Cloud? =

The plugin contacts the configured Cloud service when an administrator saves and verifies Cloud settings, when a local Npcink component explicitly uses the Cloud runtime client, when entitlement or status summaries are refreshed, or when optional monitoring is enabled and flushed.

= Is monitoring enabled by default? =

No. Monitoring requires explicit administrator opt-in and verified Cloud settings.

= What data can monitoring send? =

Monitoring sends operational metadata only, such as plugin slug/version, event kind, status, timing, error code, route, proposal id, ability id, correlation id, counters, and latency.

For supported editor journeys, this may show that generation started,
succeeded, failed, was retried or accepted, and whether an accepted result was
saved or abandoned. Monitoring uploads are associated with the configured
Cloud site, but do not include raw WordPress user or post IDs.

= Does monitoring upload prompts, content, or raw payloads? =

No. Metadata-only monitoring is designed not to upload prompts, generated content, article body content, media bytes, raw request or response payloads, provider credentials, Cloud API secrets, passwords, cookies, nonces, Authorization headers, database names, table names, or filesystem paths.

= How is monitoring data used? =

Monitoring data is used to diagnose failures, measure reliability, and improve
Npcink features. It is not used for advertising, individual user scoring,
automatic approval, or automatic WordPress content changes. Monitoring is off
by default and an administrator can turn it off at any time.

= Can media derivative jobs send media data to Cloud? =

Only when local host code explicitly invokes the media derivative transport. In that case, the request may include a short TTL source artifact descriptor and bounded derivative parameters from a local read-only ability output. Cloud credentials and signed headers are added by the addon transport and are not copied into the ability payload.

= Does this plugin write Cloud recommendations into WordPress? =

No. Final WordPress writes must go through local Core proposal, preflight, approval, and apply paths.

= Where can I review the Cloud service terms and privacy information? =

Terms of Service: https://cloud.npc.ink/terms/en/terms.html

Privacy Policy: https://cloud.npc.ink/terms/en/privacy.html

Data Retention: https://cloud.npc.ink/terms/en/data-retention.html

== Screenshots ==

1. Verified connector overview with compact plan, points, and Site Knowledge article capacity.
2. Local permissions for WordPress AI and Site Knowledge delivery.
3. Site Knowledge delivery status and bounded Cloud-owned index actions.
4. Advanced troubleshooting with service detail, runtime runs, and connection recovery.

== Changelog ==

= 0.2.0 =
* Narrowed the addon to bounded Cloud connector and artifact transport facades.
* Moved media recognition continuation ownership to Workflow Toolbox.
* Removed terminal callback delivery and cleared legacy media recognition state.

= 0.1.9 =

* Add an optional anonymous cohort identifier for separating invited-user observations from earlier technical test events.
* Keep cohort declaration local to WordPress configuration, reject invalid values, and document that identifiers must not encode user or content identity.

= 0.1.8 =

* Add opt-in, privacy-safe editor journey events for generation, recovery, acceptance, and save outcomes.
* Clarify exactly what monitoring sends, how site-associated metadata is used, and which sensitive data is never collected.
* Tighten monitoring-consent gates for editor quality evidence and prevent duplicate extensions on imported AI images.

= 0.1.7 =

* Replace the public concrete runtime client seam with bounded scenario-specific connector facades.
* Improve connection, Site Knowledge, and read-only runtime result feedback while preserving local WordPress write ownership.
* Refresh WordPress AI localization compatibility and the verified release package.

= 0.1.6 =

* Keep a valid Cloud connection credential when the site binds without an available active-site slot.
* Show a bounded "Connected, activation required" state and direct administrators to Cloud for activation.

= 0.1.5 =

Show actionable Cloud authorization exchange errors, including active-site capacity and expired authorization recovery, instead of reporting every rejected exchange as a missing connection key.

= 0.1.4 =

Point the Cloud Portal authorization entry and Open Cloud actions at `/portal` after the Portal workspace merge, and refresh the public onboarding checklist.

= 0.1.3 =

Simplify the admin overview and Site Knowledge quota display, align localized quota copy, harden Cloud authorization redirects, and refresh release assets.

= 0.1.2 =

Refine bounded Site Knowledge Cloud transport, WordPress AI connector request-log compatibility, Cloud runtime contract reuse documentation, and Plugin Check release validation.

= 0.1.1 =

Refresh Cloud connection status actions, entitlement summary caching, WordPress AI connector integration, zh_CN strings, and release packaging checks.

= 0.1.0 =

Initial standalone connector skeleton.
