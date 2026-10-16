# Cloud preflight and 0.4.1 release readiness — 2026-10-16

## Decision

The Cloud preflight is complete for Addon release preparation. The result does
not authorize WordPress.org SVN publication; that remains a separate operator
confirmation after the final package and release gates pass.

The checks used the live M4 preview reached through the existing `18010` SSH
tunnel. The local Docker `8010` development database was not used as Cloud
production-like evidence because it is a separate development stack.

## Live Cloud evidence

Observed at `2026-10-16T04:51:00–05:01:19+00:00` UTC:

- M4 accepted `master` revision: `fe9207965f4a5049f80736019114f360186ec96b`;
  source was clean and the health endpoint returned 200.
- Site: `site_magick-ai-local`, active, URL `https://magick-ai.local`.
- Addon: 0.4.1, `zh_CN`; local delivery remained enabled and verified.
- Cloud status read through the signed Site Knowledge status contract was
  `fresh / ready`, with quota status `ok`, 2,130 indexed documents, 2,362
  indexed chunks, 0 truncated documents, 0 skipped documents and 0
  quota-skipped documents.
- Cloud maintenance projection was `not_required / none`, with no request ID,
  no active batches and no last error. The local Addon bridge remained
  `idle`, buffer 0, dropped 0, last delivery successful, maintenance inactive.
- Cloud `retrieval_acceptance` was explicitly `pending` with
  `automatic=false`, no verification time and no index revision. The three
  manual title checks from the recovery record remain separate evidence and
  are not promoted to automatic acceptance.

## Quota scope

The live M4 database confirms the active published `free_v1` plan was updated at
`2026-10-15T03:41:52.371251+00:00` and carries:

- `max_vector_documents=99,999`;
- `max_media_images=99,999`;
- three active subscriptions and three active entitlement snapshots on that
  plan version.

The current site's snapshot has no account-specific resource override, so the
plan metadata is the effective account quota. This is a shared M4 development
plan setting. It is sufficient for the current 2,130-document index but does
not establish production quota policy.

## 64-record reconciliation

The current WordPress database contains 1,998 published post/page IDs. The
Cloud index contains 2,061 post/page records:

- 1 current WordPress record, ID `276731`, is password protected and is
  intentionally excluded from the public delivery manifest;
- the remaining 64 IDs exist in Cloud but are absent from the current WordPress
  database;
- the 64 records include old WordPress test fixtures and historical content,
  with most last indexed on 2026-08-22 and two on 2026-08-25.

This confirms a historical Cloud-side residue rather than a missing current
public delivery. No Cloud records were removed. Any cleanup still requires the
Cloud owner to validate site identity, export exact source keys and approve a
bounded cleanup procedure.

## Embedding and projection interpretation

All 2,362 live Cloud chunks use the explicit M4 preview embedding space
`ollama-m4-embedding:qwen3-embedding:0.6b`, with 1024 dimensions. The status
projection continues to expose the fixed production maintenance target
`siliconflow:BAAI/bge-m3`. The Cloud maintenance code only requests a full sync
for an embedding mismatch while an active maintenance lifecycle exists; the
current lifecycle is `not_required`, so no rebuild is warranted for this
preview observation.

This is a Cloud-owned projection detail. The Addon keeps the status read-only,
does not schedule an acceptance job and does not initiate rebuild or cleanup.

## Release consequence

The preflight has no Addon runtime blocker for preparing the 0.4.1 package. The
next step is to run `composer release:verify`, rebuild and verify the exact
candidate ZIP, and record the result. WordPress.org SVN upload, API/download
verification and zh_CN directory-cache checks remain a later separately
confirmed operation.

Evidence is retained locally under ignored
`build/cloud-preflight-2026-10-16/`, including the fresh Addon projections,
M4 database summaries, document-ID comparison and bounded 64-record audit.
No credentials, secrets, full content payloads or Cloud writes are included.

## Boundary and rollback

This preflight changed no source, public contract, Cloud quota, index record,
WordPress object or release artifact. Cloud remains the owner of index
execution, lifecycle, quota and freshness policy; WordPress remains the local
content and control-plane owner. Removing this document and its ignored local
evidence is the complete rollback.
