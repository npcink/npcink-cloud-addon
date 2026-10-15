# 0.4.1 final release record — 2026-10-14

## October 15 dependency and browser follow-up

The current reviewed source baseline is `master@67adbf4`, after PR #273
(`0352ca1`) recorded quota recovery and development-only dependency PRs
[#261](https://github.com/npcink/npcink-cloud-addon/pull/261) (`cc625c4`,
PHPStan 2.3.1) and
[#262](https://github.com/npcink/npcink-cloud-addon/pull/262) (`67adbf4`,
WordPress stubs 7.1.2) merged. Both dependency PRs passed all 13 final checks;
#262 was rebased and retested after #261. Maintainer rebases exposed missing
PR-body sections; completing the existing template restored that check without
changing its exemption or validation rules. Earlier failures are historical.

After installing the merged lock, local `composer release:verify` and
`composer run stan` passed. The 51-file ZIP still matches the candidate SHA256
below; development dependencies do not enter the package. The four source
version headers remain 0.4.1. No WordPress.org upload or Git release tag was
created.

A fresh Edge check on `magick-ai.local` (WordPress 7.1.3, `zh_CN`, Addon 0.4.1)
confirmed the Chinese stale-cache warning, the subsequently refreshed overview,
and the connection-management page. The Site Knowledge page initially showed
one pending update; a read-only local snapshot at `2026-10-15T08:25:59+00:00`
reported idle, buffer 0, dropped 0, maintenance idle and last delivery successful
at `2026-10-15T08:04:06+00:00`, with WP-Cron enabled. This records recovered
local delivery state, not a new Cloud indexing or retrieval-acceptance claim. No connection setting, delivery intent or optional Provider batch was
submitted. Ignored local screenshots, accessibility snapshots and gate logs
are retained in `build/closeout-recheck-2026-10-15/`.

The read-only central matrix at `2026-10-15T08:19:52+00:00` found all seven
default workspaces clean, with gates `not_run` and M4 acceptance
`not_requested`. It does not replace a full matrix: Toolbox #269 and the
shared local OCR publisher candidates remain unresolved, and Cloud is still
on a topic branch. Wait for those owners' stable clean window before the
full cross-repository run. GitHub OCR remains disabled; no paid OCR retry was
made for these dependency updates. #260 remains unmerged.

The following sections retain their original dated observations. This update
supersedes their outstanding dependency and expired-login status only;
publication still requires separate operator confirmation.

## October 15 status update

The [quota recovery closeout](site-knowledge-quota-recovery-closeout-2026-10-15.md)
supersedes the connection/quota failure snapshot below. On clean
`master@122ac79` (PR #272, all 13 checks successful), public ID coverage was
1,997/1,997 and bounded title retrieval passed 3/3. The remaining 64 historical
post/page records are absent from the current local database and were not
deleted. The 99,999 quota belongs to shared development Free plan `free_v1`;
it is not an account-only override. Automatic acceptance is an absent-field
fallback, not evidence of a running Cloud check.

October 15 release verification and PHPStan passed. Two fresh package builds
matched the 51-file SHA256 below, replacing the stale local artifact.
WordPress.org publication is still pending separate confirmation. Dependency
PR #262's earlier download error is resolved; #261 and #262 have green
13-check rollups. The older failure details below remain historical evidence.

## Scope and source

The runtime release baseline is `6db6638184e4dc0d762480a4933376826c40a59a`,
merged in [PR #271](https://github.com/npcink/npcink-cloud-addon/pull/271).
The follow-up changes only the readme changelog and release evidence. Plugin
header, version constant, Stable tag and bundled translation headers are 0.4.1.
Public PHP/REST interfaces, Cloud endpoint allowlist, stored fields, credential
boundaries and WordPress content-write boundaries are unchanged.

The current changelog includes stale entitlement status, 60-second one-time
feedback, complete status-fragment replacement, bounded recovery of dropped
public-content delivery, independent failure preservation, acceptance-count
validation and fixed Chinese notice fallbacks. Local delivery completion is
not Cloud indexing completion.

## Candidate package

The existing pre-readme-update package at the runtime baseline contained 51
files, each compared with both the manifest and current source. Its SHA256 was
`b0d290bd1c5bd65d6275f9a48f001ef4f098ea0bc11dea2fbcaa168389dc21f7`.
It is superseded by the rebuilt package for this readme update. The older
`5ac5bc5287d6eb6eb2bc4d18ed1998637287c2df7e6e1f92e79f5347351594a7`
package predates the final refresh/recovery closeout and must not be uploaded.

The rebuilt ZIP and manifest both report 0.4.1 and 51 files. Two consecutive
builds produced the same SHA256, and both passed `package:verify`:

```text
7153f1d7a4eb5cea42033a6133bc205461276c643098026dd9e70f986aad82a3
```

This is the current candidate package; its matching source/PR and complete
CI must be verified again before the separate upload confirmation.

## Verification and evidence

- PR #271: all 13 reported GitHub checks succeeded, including Release static
  gates, PHPStan and the PHP/WordPress AI compatibility matrix.
- Clean runtime baseline: `composer test:all` and `git diff --check` passed
  on 2026-10-14.
- This readme/documentation follow-up: `composer validate --no-check-publish`,
  `composer release:verify`, `composer run stan`, repeated `package:release`
  and `package:verify` passed. `release:verify` includes full tests, AI plugin
  localization audit, WordPress.org, JS (19/19), POT freshness, package gates
  and strict Plugin Check. The audit/Plugin Check used the existing
  `magick-ai.local` WordPress installation and its installed AI 1.4.0 plugin.
- Playground is not applicable to this follow-up: bootstrap, activation,
  public API, default credential state and WordPress/PHP floors are unchanged.
  The latest cited Tier B evidence remains 2026-10-10, WordPress 7.0.4/PHP 8.2.
- Administrator integration evidence is retained in the 2026-10-13 Site
  Knowledge recovery and 2026-10-14 refresh closeouts. This follow-up does not
  change their runtime surface. The administrator's reported failure and
  recovery checks are the non-AI evidence anchor; automated tests do not replace
  that report.
- The retained text Provider report generated at `2026-10-12T09:30:20+00:00`
  has 14 scenarios, `passed=14`, `failed=0`, no detected WordPress writes and
  passed quality checks. Cloud revision was `21d58e0d412a`, AI plugin 1.4.0.
  Earlier 15/15 wording is corrected in the release log. No Provider batch was
  repeated; optional comment, vision and image-generation lanes remain deferred.

## Cross-repository status

The full central `composer quality:matrix:run` completed successfully at
`2026-10-14T03:18:28+00:00` (exit 0). All seven main workspaces were clean,
neither ahead nor behind their tracked refs, and every gate passed. A post-run
SHA/status check and HEAD reflogs confirmed no branch movement during the run.

| Repository | Branch | Verified SHA | Gate |
| --- | --- | --- | --- |
| Toolkit | master | `b3d4bcb199f4f307d3c0d96da8141105eadf8832` | passed |
| Core | codex/docs-week-2026-10-13 | `7c4533fe4b3991abda8e0f69ee813cc313f29457` | passed |
| Adapter | master | `d4a6ed12b813288b11b1890b185f6dd55dfe32ab` | passed |
| Toolbox | detached | `a7dc1edc908e71d67bdd06d3d1f29b276e079dce` | passed |
| Addon | master | `6db6638184e4dc0d762480a4933376826c40a59a` | passed |
| Eval Lab | main | `5362655d9f27f9db9a3bcc8a7717e75ac1d9659f` | passed |
| AI Cloud | detached | `d97dfd4f2c08914a45b7bf8364fd336a38d3f596` | passed |

The Addon/Toolbox release-tooling candidates were not in these main-workspace
SHAs. Refresh the matrix after their merges for final rollout closeout. The
Cloud source gate used exact-SHA GitHub CI; M4 deployment/runtime acceptance
was not requested. This source result cannot claim a live Cloud service.

The Toolbox chat owns the shared local OCR publisher gate. This chat owns the
Addon adapter and release record. GitHub OCR remains paused until manual-only
configuration is merged and verified. A provider timeout or partial review
does not constitute complete advisory evidence; paid retries must respect the
shared daily, cooldown, token and time limits.

## Publication and remaining work

The WordPress.org API still reports 0.4.0 as of 2026-10-14. Do not upload trunk
or `tags/0.4.1` without the operator's separate publication confirmation.
Before that confirmation, require the final package/release verification and
complete PR check rollup. After publication, verify the API and downloaded ZIP,
the four zh_CN GlotPress sets, 0.4.1 language-pack generation and installation,
and Chinese directory cache propagation. The four sets were already complete
on 2026-10-12; old Waiting work is not an outstanding task.

Dependency PRs #260, #261 and #262 stay outside the frozen runtime package.
The wordpress-stubs PR #262 failed while downloading WordPress AI 1.4.0 with
HTTP 403; resolve or rerun that download before attributing failure to stubs.
Site Knowledge local delivery had completed in the 2026-10-13 recovery evidence.
A new read-only check at `2026-10-14T03:22:32+00:00` on `magick-ai.local`
found three buffered updates, 25 dropped changes, `last_delivery_ok=false`, an
idle maintenance cursor and last successful delivery at `2026-10-13T14:14:16Z`.
The retained Cloud quota snapshot was stale (169 indexed documents, 10,000
limit, refreshed at `2026-10-13T07:49:17Z`). Two bounded read-only status requests
returned `cloud_runtime_request_failed`; the second confirmed cURL error 7
(connection refused). No delivery, rebuild, indexing or
Provider call was initiated. Current Cloud indexing completion is unverified.
Restore/verify the configured Cloud transport before attempting recovery,
and refresh the projection without discarding these local failure facts. Cloud-owned
artifact continuation, partial success and capability refresh policy, plus
larger B-class UX and upstream shim contribution, remain separate work.

## Rollback

Revert this release-record PR to remove the readme/documentation update.
Runtime fixes remain reversible by their own PRs without a migration.
If 0.4.1 is published and a defect is found, release a later patch version;
do not remove an already published SVN tag.
