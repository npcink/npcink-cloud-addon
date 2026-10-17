# 0.4.2 paired Cloud release readiness — 2026-10-17

Status: release candidate preparation; neither production deployment nor
WordPress.org publication is authorized by this record.

## Frozen source and package scope

The Cloud application candidate remains reviewed master
`ebb8c3c2512fc551d5bd129661655f6447afb809`, prepared as local production-based
single commit `056891c1b9405b69e493ab7988b6fe3f7e188142` on
`release-fix/reviewed-tree-20261017-ebb8c3c2`. Its tree is
`926193d59e774424649ce0484bacfa9adf1711ed`, and its 91-path patch is byte-identical
to the reviewed production-to-master delta. All 48 historical merge conflicts
are resolved by that exact reviewed tree. It is not a production branch or a
claim of production CI. Cloud PR #1130's later rollup optimization is excluded
from this frozen application envelope; it remains preserved on master.

Cloud tooling [PR #1131](https://github.com/npcink/npcink-ai-cloud/pull/1131)
merged into master as `b9a173d6f7e1b12730ccaeb30dc2e68d14db9c4c` and
is separate: it supplies the bounded integration preparer and its contract,
without adding tool/policy changes to the frozen application candidate.

Addon application source is reviewed master
`41a7845457ddbc2a4331e490fb183668bbc3c8f7` (PR #286), containing PR #283's
bounded ranking feedback fields, PR #284's historical public-content
reconciliation repair, and PR #286's AI setup guidance and knowledge usage
errors. This preparation changes only 0.4.2 version identities, changelog,
translation headers/compiled metadata, and this record. It does not alter
runtime code, activation, credential state, WordPress/PHP floors, or endpoints.

The public WordPress.org metadata read on 2026-10-17 reported 0.4.1 and no
0.4.2 download. The 0.4.2 package is a local candidate, not a published tag.
Header, constant, Stable tag, POT/PO/MO metadata, package manifest, ZIP members
and hashes must agree before any distribution. Readiness receipts retain the
exact post-merge Addon SHA and package SHA256 outside disposable worktrees.

## Local package receipt

On 2026-10-17 the 52-file 0.4.2 ZIP passed source-member comparison and
version coherence with SHA256
`3ff923bc0f75098280de19a5b3b4842662c57b0b0e687610a7c83727cf95cc04`.
All 648 translated message mappings are unchanged. Composer validation,
PHPStan, and every release-verification sub-gate passed. The initial combined
command reached Plugin Check after all earlier sub-gates passed, then failed
because the isolated checker bootstrap omitted its own plugin constants.
Loading only the checker after WordPress bootstrap repaired that local fixture;
the focused strict Plugin Check passed on the same ZIP. The combined first run
is retained as failed evidence; successful earlier checks were not replayed.
Required PR checks and exact post-merge source identity remain separate receipts.

## Required order and stop conditions

1. Finish the source PR checks and retain the reviewed candidate identities.
   Recheck the live Cloud production ref against
   `2af00741a2ce06ea56a78e62864d4a35d86c66ab`. If it changed, stop and prepare a
   fresh integration candidate; never reset or force-push the old candidate.
2. Enter Cloud production-promotion preparation only under its separate
   authorization and normal approval contract. Production-PR CI and exact
   production-push CI/build/scan remain required. Master/M4 success and local
   tree equality do not supply those production receipts.
3. Confirm exact Linux/AMD64 bundle/security evidence, protected configuration
   inventory, certificate/database readiness, prior image/bundle identities,
   data backup and rollback records through the existing Cloud release policy.
   No production secret or configuration value belongs in this record.
4. Keep Addon 0.4.2 undistributed until the paired Cloud service is live and its
   authenticated, zero-paid readback checks pass. Old Cloud rejects the new
   ranking feedback fields; preserving buffers does not make that server
   schema compatible.
5. Before enabling or resuming Site Knowledge delivery, confirm its Cloud
   Provider/indexing budget and observe one bounded historical-reconciliation
   batch. Existing opted-in sites can resume older public-content deliveries
   after upgrade; local delivery success and Cloud indexing completion are
   separate evidence. Do not trigger a catch-up merely to manufacture evidence.
6. Distribute the exact verified Addon package only after separate publication
   approval. Verify the downloaded package and version afterward; do not
   overwrite an already-published version/tag. Keep WordPress.org language-pack
   and the local bundled-catalog checks separate.
7. Run human production acceptance in order: email registration and re-login,
   consistent account/Free entitlement, site connection, official AI setup
   guidance, knowledge usage/read failure states, scoped runtime profile save,
   canonical Provider budget selection, and safe error/correlation evidence.
   Real generation, manual adoption/save/reload and point accounting require a
   separately confirmed Provider budget and the operator's paid-call approval.

## Zero-paid acceptance and evidence

Every row above is either independently evidenced or remains pending. The
0.4.2 preparation must pass `composer release:verify`, PHPStan, package source
comparison, version coherence and the full PR check rollup. Playground is not
required for this metadata-only preparation: no bootstrap behavior or platform
floor changed. Retain the fresh 2026-10-17 isolated WordPress 7.0.4/PHP 8.2
missing/inactive/active AI guidance evidence from PR #286; it does not replace
production WordPress 7.1.3 acceptance.

The release check uses local WordPress for strict Plugin Check with ordinary
plugins/themes skipped, WordPress external HTTP blocked, cron disabled and a
temporary unpacked package removed by the existing checker. It does not call a
model, submit content, change production configuration, or install the package
on a live site. Translation message mappings must remain identical across the
version-only catalog update. Cross-repository closeout uses the central
Toolbox matrix for Cloud and Addon with explicit isolated worktree paths.

The operator's 2026-10-17 production report is the non-AI evidence anchor:
registration and verification succeeded, Free showed 300 points/one site,
re-login and site connection succeeded, but generation failed and knowledge
usage could not be read. This is a verified user report of the problem, not a
claim that real writing acceptance subsequently passed. No paid run is repeated
for this release preparation.

## Rollback

Before publication, retain the candidate without merging it into production or
uploading it; no environment rollback is required. After a paired deployment,
stop new ranking field senders or restore the prior compatible Addon package
before restoring older Cloud API images. Preserve feedback/delivery buffers,
reconciliation cursors, run/claim/usage ledgers, and existing WordPress content.
Never erase data or replay calls as rollback. Revalidate the prior Cloud bundle
and active image identities separately; the source ref alone is not sufficient.
If Addon 0.4.2 has been publicly published, fix forward with a newer version
instead of replacing its SVN tag.

This record does not authorize production branch promotion, host staging,
cutover, configuration edits, WordPress.org upload, or paid Provider calls.
