# Site Knowledge quota recovery and release recheck — 2026-10-15

## Scope and evidence

The operator restored the Cloud connection and increased quota before this
recovery. Addon source was clean `master@122ac79`, including merged PR #272
with all 13 reported checks successful. Cloud liveness reported development
`m4-preview@0bb4d1fcc2dc`, `source_dirty=false`. These are local-preview
observations, not production acceptance.

Retained operational evidence is in the ignored local directory
`build/quota-recovery-2026-10-15/`: `report.json`, `coverage-report.json`,
`retrieval-report.json`, `final-status.json`, per-run results, manifest and
read-only audit reports. This document records aggregate evidence; credentials
and full content payloads are excluded from Git and the release package.

## Recovery result

The existing bounded start/refresh cursor completed at
`2026-10-15T04:11:44+00:00`, request
`ada8859b-6ea1-4533-81a7-774f07f15d85`. No rebuild or explicit delete was issued.

| Observation | Result |
| --- | --- |
| Local selected IDs | 1,998 |
| Password-protected content excluded from delivery | 1 |
| Eligible public documents | 1,997 |
| Cloud runs | 10/10 succeeded |
| Accepted and indexed documents | 1,997 |
| Generated/refreshed chunks | 2,215 |
| Failed, truncated, skipped, quota-skipped documents | 0 each |
| Complete ID coverage | 1,000/1,000 + 997/997; none missing |
| Final local delivery | idle; buffer 0; dropped 0; last delivery successful |
| Cloud status | ready; fresh; 2,130 documents; 2,362 chunks |

The local selected/sent cursor counts are not Cloud acceptance counts. One
refresh result reported 78 replaced/deleted entries under existing refresh
behavior; no operator cleanup was performed.

At `2026-10-15T04:11:14+00:00`, three title queries covering recent, middle
and older public content returned the expected document at rank 1 (3/3), with
`evidence_gate=passed`. Sample IDs were 278528, 11728 and 4109. All used
`ollama-m4-embedding:qwen3-embedding:0.6b`; reranking was disabled. These are
bounded title-retrieval checks, not a full semantic-quality evaluation or
Cloud automatic acceptance. No optional comment, vision, image-generation or
text Provider batch was repeated.

## Read-only discrepancy diagnosis

The database audit at `2026-10-15T04:48:17+00:00` found 2,015 post, 46 page
and 69 media documents. The 2,061 post/page records exceed the current eligible
manifest by 64. Comparing those 64 IDs with the current WordPress database
at `2026-10-15T04:49:06+00:00` found every ID absent locally: 50 posts and
14 pages. Of these, 62 were last indexed on August 22 and two on August 25.
Examples include old theme test fixtures, but not every record is a fixture.
This is a historical-record discrepancy, not missing delivery of the current
public manifest. No records were removed. Before cleanup, the Cloud owner
must validate site identity, export the exact source keys and define a bounded
supported cleanup and recovery procedure; do not prune by count alone.

All 2,362 stored chunks use the same Ollama embedding model (post 2,245,
page 48, media 69); no mixed embedding space was observed. The deployed
revision's `maintenance.target_embedding_space_id()` nevertheless returns the
fixed production profile `siliconflow:BAAI/bge-m3`, including when maintenance
is `not_required` and action is `none`. Its mismatch recovery is conditional
on an active fixed-profile lifecycle. The preview target projection needs
Cloud-owned clarification; it is not evidence that these vectors are corrupt
and is not a reason to initiate a rebuild.

At that same deployed revision, `SiteKnowledgeService.status()` does not emit
`retrieval_acceptance`. The Addon bridge defaults the absent field to
`pending`, `automatic=false`, with no verification time or revision. That
fallback does not prove an automatic acceptance job exists or is still
running. Follow up on the Cloud/Addon optional-field semantics and the
"Waiting for automatic validation" presentation separately. Do not mark the
manual 3/3 retrieval checks as automatic acceptance or add a WordPress-owned
acceptance scheduler.

## Quota scope

Authenticated Cloud Admin showed account `vector_documents=99,999`.
Read-only database checks at `04:51:31–04:51:46 UTC` confirmed the value is on
the published Free plan version `free_v1`, updated at
`2026-10-15T03:41:52+00:00`; `max_media_images` is also 99,999. Three active
subscriptions use that version. Their active entitlement snapshots have no
`max_vector_documents` override, so the resolver falls back to plan metadata.
This is a shared development-plan change, not an account-specific exception.
The site's 10,000-document implementation cap is a different limit.

No billing configuration was changed by this task. Before production use,
the operator should decide whether the shared Free allowance is intentional
or whether this test account needs an account-scoped allowance. This audit
does not establish production quota values.

## 0.4.1 recheck and remaining gates

On October 15, `composer validate --no-check-publish`, `composer run stan`,
`composer release:verify`, a second `package:release`/`package:verify`, and
`git diff --check` passed on clean `122ac79`. Release verification included
full tests, the AI localization audit, WordPress.org rules, JavaScript tests
(19/19), POT freshness, manifest checks and strict Plugin Check.

The stale local ZIP was replaced. Both new builds contain 51 files and match
the previously recorded candidate SHA256:

```text
7153f1d7a4eb5cea42033a6133bc205461276c643098026dd9e70f986aad82a3
```

This follow-up changes documentation only. Tier B Playground and a new Tier C
UI test are not required for that change: bootstrap, activation, public API,
default credential state, supported baselines and administrator UI are
unchanged. The October 10 Tier B and October 13–14 UI evidence remain subject
to the 30-day/surface-change freshness rule. An additional live browser
recheck reached an expired local login session; no successful new UI evidence
is claimed by this record. The recovered Cloud integration evidence above is
new Tier C evidence. The operator's connection/quota restoration report is
retained as a human observation, not independent semantic-quality acceptance.

WordPress.org still reports 0.4.0. Uploading trunk and `tags/0.4.1` requires
separate publication confirmation, followed by API/download checks and the
zh_CN language-pack/cache checks. The four GlotPress sets completed on
October 12 do not need to be treated as untranslated work again.

Toolbox remains the shared local OCR gate owner. Its candidate review is
incomplete and the shared daily attempt allowance is exhausted; no new paid
OCR or retry was run for this closeout. The Addon adapter candidate remains
unmerged pending that dependency and complete review. Preserve default limits
and disabled GitHub OCR; do not substitute partial or older review evidence.
Dependency PRs #261 and #262 now have successful 13-check rollups; #262's
earlier download failure is resolved. They remain separate from this release
evidence change. Reassess #260 after the local/manual OCR policy is settled.

The October 14 seven-repository matrix is historical evidence. Run a new full
matrix only after the relevant candidates merge and a stable clean window is
confirmed. No new full matrix or WordPress.org publication occurred here.

## Boundaries and rollback

No source, public PHP/REST interface, Cloud endpoint allowlist, persistent
field, credential or WordPress write boundary changed. Cloud index lifecycle
and commercial truth remain Cloud-owned. Revert this documentation change to
remove the record; it performs no data migration. Historical Cloud cleanup,
quota-policy changes and publication are separate operations with their own
confirmation and recovery procedure.
