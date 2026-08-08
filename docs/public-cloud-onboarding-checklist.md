# Public Cloud Onboarding Checklist

Status: active smoke checklist for public Cloud readiness.

Closeout context: see
`docs/public-cloud-readiness-closeout-2026-07-02.md`.

Use this checklist before treating the WordPress.org package as ready for
public Cloud connections. This is an addon-side verification checklist only; it
must not become a Cloud operations console or a second control plane.

## Scope

This checklist verifies that a fresh WordPress site can use the packaged addon
to reach the public Npcink Cloud authorization flow.

It covers:

- the environment-aware default Cloud Base URL;
- the Cloud Portal authorization entry;
- callback exchange and immediate signed verification;
- read-only entitlement and status projection;
- opt-in metadata-only monitoring behavior.

It does not cover:

- Cloud service-plane provisioning implementation;
- Cloud billing, invoice, or key lifecycle operations;
- proposal, approval, rollback, or WordPress write ownership;
- Site Knowledge index lifecycle, rebuild policy, or freshness truth.

## Preflight

Run the addon gates:

```bash
composer run test:all
git diff --check
rg "/v1/runtime/workflows/runs|wp_insert_post|wp_update_post" --glob '*.php' --glob '!build/**' .
```

Run WordPress.org release checks with a working WP-CLI binary:

```bash
WP_CLI_BIN=/opt/homebrew/Cellar/wp-cli/2.12.0/bin/wp composer run release:verify
```

Confirm public Cloud entrypoints:

```bash
curl -fsS https://cloud.npc.ink/health/live
curl -fsSI https://cloud.npc.ink/portal
```

Expected result:

- `/health/live` returns a minimal healthy production response.
- `/portal` reaches the Portal authorization path or redirects to login.

## Fresh Public Site Smoke

Use a WordPress site that has no stored `npcink_cloud_addon_settings` option.

1. Install the packaged addon.
2. Activate `Npcink Cloud Addon`.
3. Open `Npcink > Cloud Addon`.
4. Confirm the unresolved Cloud value is `https://cloud.npc.ink/`.
5. Confirm the primary action opens
   `https://cloud.npc.ink/portal` with `connect=wordpress-addon`,
   `site_url`, `site_name`, `return_url`, and `state` query parameters.
6. Complete Cloud Portal login and site authorization.
7. Confirm Cloud returns to `wp-admin/admin-post.php` with the expected action,
   state, and one-time authorization code.
8. Confirm the addon exchanges the code at
   `/portal/v1/addon-connections/exchange`.
9. Confirm the addon stores the returned Cloud Base URL and Cloud API Key
   wrapper, then immediately verifies the signed connection.
10. Confirm the default page shows verified connection state and a read-only
    entitlement summary.

## Local Development Smoke

Local WordPress environments may still resolve the default Cloud Base URL to
`http://localhost:8010/`. This is expected when `wp_get_environment_type()`
returns `local` or the site host is `localhost`, `127.0.0.1`, `::1`, or
`.local`.

To simulate the public package path from a local WordPress site, set:

```php
define( 'NPCINK_CLOUD_ADDON_DEFAULT_BASE_URL', 'https://cloud.npc.ink/' );
```

Remove that override after the smoke test unless the site is intentionally
connected to public Cloud.

## Monitoring And Data Boundary

Before enabling monitoring, confirm no plugin event upload is sent.

After enabling monitoring, confirm uploads remain metadata-only. Monitoring may
send operational metadata such as plugin slug/version, event kind, status,
timing, error code, route, proposal id, ability id, correlation id, counters,
and latency.

Monitoring must not send prompts, generated content, article body content,
media bytes, raw request or response payloads, provider credentials, Cloud API
secrets, passwords, cookies, nonces, Authorization headers, database names,
table names, or filesystem paths.

## Boundary Checks

The addon may open Cloud Portal and may display read-only Cloud detail.

It must not:

- create or own Core proposals;
- approve, apply, replace, rollback, or write WordPress content;
- expose split signing credentials;
- create a workflow engine, scheduler truth, repair console, or task queue
  truth;
- manage billing truth or key lifecycle operations;
- own Site Knowledge index execution, lifecycle, rebuild policy, or freshness
  policy.

If a required fix crosses one of those lines, move the work to local Core,
Adapter, Toolbox, or Cloud service-plane code before releasing the addon.
