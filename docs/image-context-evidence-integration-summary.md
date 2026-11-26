# Image Context Evidence Integration Summary

Status: accepted as a bounded auxiliary evidence path.

Date: 2026-06-26

## Context

The WordPress AI connector can expose text and image scene wrappers after Cloud
settings are verified, but it intentionally does not register a
`wpai_preferred_vision_models` override. Vision and Alt Text defaults stay with
the WordPress AI plugin until there is a separate bounded vision scene contract.

Media ALT/caption review sets still have a real weak point: when local media
metadata is empty, filename-like, or duplicated, Toolbox cannot produce useful
review candidates from metadata alone. Reusing Cloud-owned image context
evidence addresses that weak point without making this addon a generic vision
provider or a WordPress media writer.

## Decision

Keep image context evidence as an optional auxiliary capability:

- Toolbox remains the operator-facing review-set owner.
- Toolbox first builds the local metadata-only
  `media_alt_caption_review_set.v1`.
- If candidates are blocked as `candidate_quality_insufficient`, Toolbox may
  emit an `image_context_evidence_request.v1` packet for up to 10 weak items.
- If this addon exposes `request_image_context_evidence()`, Toolbox may call it
  once and rebuild the local review set with returned `image_context_evidence.v1`.
- Returned visual summary, scene, objects, and visible text may be used only as
  candidate basis.
- Every selected item still requires human visual confirmation.
- No media metadata is changed by Toolbox or this addon.

## Why This Is Worth Keeping

This is worth keeping because it improves the weakest media review cases while
reusing existing seams:

- It reuses Toolbox review-set contracts instead of adding a new product flow.
- It reuses the Cloud Addon runtime client instead of adding provider-specific
  vision code to WordPress.
- It reuses `POST /v1/runtime/execute` instead of adding a new endpoint family.
- It gives operators better clues for weak media libraries without adding a
  local model, bundled dataset, queue, proposal creator, or write path.
- It keeps Cloud as runtime/detail only and local WordPress/Core as the owner of
  approval, proposal, preflight, and final writes.

The value is practical but bounded: reduce manual inspection effort for weak
media metadata, not automate ALT/caption writes.

## Cloud Addon Contract

`Npcink_Cloud_Runtime_Client::request_image_context_evidence()` accepts only the
Toolbox `image_context_evidence_request.v1` artifact. The request must keep:

- `write_posture=suggestion_only`
- `direct_wordpress_write=false`
- `no_local_model=true`
- `no_media_write=true`
- `source_policy=bounded_media_urls_for_visual_context_only`

The runtime payload is a bounded hosted-runtime call:

- endpoint: `POST /v1/runtime/execute`
- `ability_name=npcink-cloud/image-context-evidence`
- `profile_id=vision.ai`
- `execution_kind=image_context_evidence`
- `storage_mode=result_only`
- `policy.allow_fallback=false`

The addon may send only bounded media URL or thumbnail URL, attachment id, MIME
type, title/filename context, and local candidate-quality flags. It must not
send provider credentials, Cloud signing data, raw WordPress database fields,
local filesystem paths, or media bytes.

The normalized response must:

- return `image_context_evidence.v1`;
- filter evidence to requested attachment ids;
- force `write_posture=suggestion_only`;
- force `direct_wordpress_write=false`;
- set `needs_human_visual_check=true`;
- fail closed when Cloud returns no usable evidence.

## Non-Goals

Do not expand this path into:

- a `wpai_preferred_vision_models` default;
- a generic WordPress AI vision provider;
- an OpenAI-compatible image/vision proxy;
- local image recognition;
- a local queue or scheduler truth;
- Core proposal creation;
- media metadata writes;
- media upload/download or replacement flows;
- prompt, preset, router, model-center, ability, workflow, MCP, or OpenClaw
  control planes.

If a future Alt Text apply path is needed, it must go through Abilities, Core
proposal/preflight, Adapter execution, and final local WordPress approval. This
addon must remain the signed transport/detail seam only.

## Current Implementation References

Cloud Addon:

- `includes/bootstrap.php` exposes
  `npcink_cloud_addon_request_image_context_evidence()`.
- `includes/class-cloud-runtime-client.php` validates the request contract,
  dispatches `npcink-cloud/image-context-evidence`, and normalizes
  `image_context_evidence.v1`.
- `docs/cloud-runtime-client-contract.md` defines the runtime client contract.
- `docs/cloud-addon-boundary.md` keeps image context evidence on the existing
  runtime execute endpoint and blocks local vision/write ownership.
- `tests/behavior-image-context-evidence.php` verifies fail-closed request and
  response behavior.

Toolbox:

- `includes/Provider_Client.php` builds the review set, requests optional image
  context evidence when available, and uses evidence only as candidate basis.
- `docs/media-alt-caption-review-set.md` defines the metadata-first review-set
  and optional weak-metadata evidence request.
- `assets/admin.js` displays image clues and human visual confirmation.
- `tests/run.php` contains static contract assertions for the optional Cloud
  evidence path.

## Verification

Before changing this path, run at minimum:

```bash
composer run test:all
git diff --check
rg "/v1/runtime/workflows/runs|wp_insert_post|wp_update_post" --glob '*.php' --glob '!build/**' .
```

For cross-repo behavior changes, also run the central matrix from
a checkout of `npcink-workflow-toolbox`:

```bash
composer quality:matrix
composer quality:matrix:run
```
