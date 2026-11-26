# Site Knowledge Index Management Closeout

Status: accepted.

Date: 2026-06-30.

## Context

The Site Knowledge index is built from WordPress public content, so the local
WordPress operator needs a clear place to review and request index actions.
Sending users to a Cloud-only page for start, rebuild, or delete would hide the
content source and make the local consent boundary unclear.

At the same time, Cloud Addon must remain a connector. It must not become a
vector database control plane, indexing scheduler, stale-index policy engine, or
WordPress write authority.

## Decision

Cloud Addon now owns the WordPress-side Site Knowledge operator surface:

- a dedicated `Site Knowledge` tab in `Npcink > Cloud Addon`;
- a local `Enable Site Knowledge delivery` consent switch;
- local entries for `Request public content refresh`, `Start indexing`,
  `Rebuild index`, and `Delete site index`;
- shallow bridge status, buffered public changes, last delivery, last index
  action, and a Cloud detail link.

The local delivery switch controls only whether this WordPress site sends
future public content changes and administrator start/rebuild requests to Cloud
Site Knowledge. It is not index lifecycle truth.

## Behavior

When delivery is enabled:

- public post/page and approved comment changes may be buffered locally and sent
  through the signed Cloud runtime transport;
- `Start indexing` sends a bounded public post/page manifest with
  `sync_mode=refresh` and `operation_source=admin_start`;
- `Rebuild index` requires typing `REBUILD` and sends public manifests with
  `sync_mode=rebuild`;
- `Delete site index` requires typing `DELETE` and sends `sync_mode=delete`
  with no documents.

When delivery is disabled:

- automatic public content buffering stops;
- manual refresh, start, and rebuild are disabled or fail closed locally;
- existing Cloud index data is not deleted;
- `Delete site index` remains available as the explicit cleanup path.

All payloads remain `write_posture=suggestion_only` with
`direct_wordpress_write=false`. WordPress content is not changed by these
actions.

## Boundary

WordPress owns:

- local administrator intent;
- local delivery consent;
- bounded public content manifests;
- shallow connector status.

Cloud owns:

- embedding execution;
- vector storage;
- index rebuild/delete execution;
- collection lifecycle;
- freshness policy;
- deep diagnostics.

Toolbox remains a Site Knowledge consumer. It may use search/status results, but
it does not own index lifecycle actions.

## Verification

Static and behavior gates passed:

- `composer run test:all`
- `git diff --check`
- `rg "/v1/runtime/workflows/runs|wp_insert_post|wp_update_post" --glob '*.php' --glob '!build/**' .`

Local WordPress smoke test also passed on
`$LOCAL_WP_PATH` (the Local WordPress site path) using the Local MySQL socket:

- the `Site Knowledge` panel renders delivery, refresh, start, rebuild, and
  delete controls;
- the rendered panel does not expose the stored Cloud secret;
- start, rebuild, and delete produce the expected Cloud runtime payloads;
- disabling delivery stops buffering and start/rebuild transport;
- disabling delivery still allows delete cleanup transport.

The smoke test used temporary verified Cloud settings and an HTTP filter to
intercept Cloud calls. Temporary settings, Site Knowledge buffer, and Site
Knowledge status options were removed after the test. The local plugin was
returned to its prior inactive state.
