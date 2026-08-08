=== Npcink Cloud Addon ===
Contributors: muze233
Tags: magick ai, cloud, hosted runtime
Requires at least: 7.0
Tested up to: 7.0
Requires PHP: 8.0
Stable tag: 0.1.4
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

== External Services ==

This plugin connects to the Npcink Cloud service configured by the site administrator through the Cloud Base URL setting.

The plugin contacts the configured Cloud service only after an administrator enters a Cloud Base URL and Cloud API Key, saves the settings, verifies the connection, or a local Npcink component explicitly uses the Cloud runtime client.

Requests may include the configured site identifier, key identifier, request timestamp, nonce, trace identifier, idempotency key, HMAC signature headers, runtime request payloads supplied by local Npcink components, metadata-only monitoring events when enabled, and read-only requests for health, run status, run result, usage statistics, entitlement summaries, observability summaries, and Agent feedback quality summaries. The stored Cloud API Key secret is used server-side for request signing and is not printed in wp-admin.

For host-supplied media derivative runtime jobs, requests may also include a
short TTL source artifact descriptor and derivative request parameters from the
local read-only ability output. Cloud credentials and signed headers are added
by the addon transport and are not copied into the ability payload.

For Site Knowledge transport, requests may include public post/page identifiers,
public content manifest metadata, approved comment change hints, local delivery
consent state, and explicit administrator delivery intents for Cloud-owned index
operations. Drafts, private posts, password-protected posts, credentials, and
raw database fields must not be sent by this transport.

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
4. Enter Cloud Base URL and Cloud API Key.
5. Click `Save and Verify`.

== Frequently Asked Questions ==

= Does this plugin create Cloud API Keys? =

No. Keys are issued by Npcink Cloud.

= Do I need a Npcink Cloud account? =

Yes. A site administrator needs a Cloud Base URL and a Cloud API Key issued by the configured Npcink Cloud service before the connector can verify successfully.

= Does this plugin display the Cloud secret? =

No. The secret is stored for server-side signing only and is never printed on the settings page.

= When does the plugin contact Npcink Cloud? =

The plugin contacts the configured Cloud service when an administrator saves and verifies Cloud settings, when a local Npcink component explicitly uses the Cloud runtime client, when entitlement or status summaries are refreshed, or when optional monitoring is enabled and flushed.

= Is monitoring enabled by default? =

No. Monitoring requires explicit administrator opt-in and verified Cloud settings.

= What data can monitoring send? =

Monitoring sends operational metadata only, such as plugin slug/version, event kind, status, timing, error code, route, proposal id, ability id, correlation id, counters, and latency.

= Does monitoring upload prompts, content, or raw payloads? =

No. Metadata-only monitoring is designed not to upload prompts, generated content, article body content, media bytes, raw request or response payloads, provider credentials, Cloud API secrets, passwords, cookies, nonces, Authorization headers, database names, table names, or filesystem paths.

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
