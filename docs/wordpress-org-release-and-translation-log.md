# WordPress.org Release and Translation Log

Status: active operational handoff.

Last updated: 2026-10-16.

This document records the WordPress.org release, assets, legal pages, and zh_CN
translation work completed for `npcink-cloud-addon`.

## 2026-10-16 0.4.1 WordPress.org publication and post-publish verification

After the separate publication confirmation, the exact 51-file candidate was
committed to WordPress.org SVN from a temporary working copy. The release
commit is `r3749721` (`Release 0.4.1`); both
[`trunk`](https://plugins.svn.wordpress.org/npcink-cloud-addon/trunk/) and
[`tags/0.4.1`](https://plugins.svn.wordpress.org/npcink-cloud-addon/tags/0.4.1/)
contain the 0.4.1 candidate. The repository head observed during verification
was `r3749723`.

The plugin API returned version `0.4.1`, last updated `2026-10-16 05:35am GMT`,
and the official download URL. The downloaded
`npcink-cloud-addon.0.4.1.zip` unpacked to 51 files with no extra, missing, or
content-different files compared with the local candidate. Archive-level SHA256
values can differ because WordPress.org rebuilds the ZIP; the decompressed file
content matched exactly. Both English and Chinese directory pages show version
0.4.1 and the 0.4.1 changelog.

The stable plugin zh_CN GlotPress page currently reports 604/604 translated,
0 untranslated and 0 waiting. Stable readme zh_CN reports 84/84 translated,
0 untranslated and 0 waiting. At the final observation
(on 2026-10-16 UTC), the WordPress translations API still returned only
the 0.4.0 zh_CN package and the expected 0.4.1 package URL returned HTTP 404.
This was the pre-propagation snapshot; the bundled 0.4.1 MO file was present
in the published package but was not counted as an official language-pack
result. The API and
`https://downloads.wordpress.org/translation/plugin/npcink-cloud-addon/0.4.1/zh_CN.zip`
were rechecked after propagation as recorded below.

Evidence is retained in ignored `build/wporg-postpublish-20261016/`, including
the SVN tag info, plugin API response, downloaded ZIP, decompressed content
comparison, GlotPress HTML, directory pages and translation API responses.
No Git source, Cloud data or WordPress content was changed by publication.

### 2026-10-16 zh_CN language-pack propagation and local installation

The official translation API now returns version `0.4.1` for `zh_CN`, and the
official package URL returns HTTP 200. The downloaded package SHA256 is
`c3a0c1512dcc82313e84360e14f41dd045052f64e0dbcea9d6380b5a22e8bca1` and
contains the expected `.po`, `.mo` and `.l10n.php` files. WP-CLI installed the
pack on `https://magick-ai.local` after temporarily switching the site locale
to `en_US`; the site was switched back to `zh_CN` after installation.

Final local verification reports locale `zh_CN`, active plugin version `0.4.1`,
the three language-pack files present with hashes matching the downloaded
official ZIP, a loaded `npcink-cloud-addon` text domain, and the fixed string
`Not recently checked` translating to `最近未检查`. Redacted evidence is saved
under `build/wporg-postpublish-20261016/` in
`zh_CN-language-pack-download-evidence.json` and
`zh_CN-language-pack-local-verification.json`.

This closes the 0.4.1 WordPress.org publication and post-publish language-pack
verification scope. Historical OCR, cross-repository matrix and Cloud-owner
decisions remain tracked in the session handoff and are outside this Addon
release closeout.

### 2026-10-16 cross-repository closeout check

After the language-pack verification, the central matrix was run from
`/Users/muze/gitee/npcink-workflow-toolbox` at
`2026-10-16T07:49:15Z` against the three primary master worktrees. All seven repository gates passed; M4 runtime
acceptance was explicitly `not_requested`. The matrix is evidence for this
clean-window check and does not decide Cloud-owned historical-record cleanup or
state-projection semantics.

The Addon, Toolbox and Cloud auxiliary worktrees were then removed. Any
worktree with uncommitted experimental files was stashed before removal, so
the cleanup did not discard local work. The three primary worktrees were left
clean. PR #260 remains open with all required checks green; its advisory
OpenCodeReview check failed because the service was rate-limited with HTTP 429
and produced no review comments.

## 2026-10-15 Dependency and Browser Follow-up

The recovery record merged in PR #273 (`0352ca1`). Development-only PRs #261
(PHPStan 2.3.1, `cc625c4`) and #262 (WordPress stubs 7.1.2, `67adbf4`) then
merged with 13/13 final checks each; #262 was retested on top of #261.
Post-install local release verification and PHPStan passed, and the candidate
ZIP retains the 51-file SHA256 in the
[final release record](041-final-release-record-2026-10-14.md).

A fresh logged-in Edge check confirmed the Chinese overview, stale-cache
warning, Site Knowledge page and connection-management page. The local
read-only delivery snapshot was idle with buffer 0, dropped 0 and a successful
last delivery. It does not supersede the Cloud-owned discrepancies or establish
automatic retrieval acceptance. No settings or manual index operation was
submitted.

The earlier follow-up CI-rollup task is complete. Remaining release work is
separate SVN publication confirmation, followed by API/download, 0.4.1 zh_CN
language-pack installation and directory-cache verification. The OCR publisher
rollout and a fresh full cross-repository matrix remain pending the respective
owners and a stable clean window. #260 remains unmerged; no paid OCR retry or
WordPress.org publication occurred in this follow-up. Older sections retain
their dated snapshots rather than describing the current dependency state.

## 2026-10-15 Recovery and Candidate Recheck

Clean `master@122ac79` includes PR #272 (13/13 successful checks). The
[quota recovery closeout](site-knowledge-quota-recovery-closeout-2026-10-15.md)
records 1,997/1,997 current public IDs indexed, 3/3 bounded title queries,
the 64 historical records left untouched and the shared development Free-plan
quota scope. Full release verification, PHPStan and two reproducible 51-file
ZIP builds passed, matching the hash in the final release record. WordPress.org
remains at 0.4.0; no SVN publication occurred. Older transport-failure snapshots
below are superseded by this dated recovery evidence.

## 2026-10-14 Final Candidate Record

The current release baseline is clean `master@6db6638` (merged PR #271),
including the refresh/recovery closeout. Its complete 13-check GitHub rollup
passed; a new local `composer test:all` also passed on 2026-10-14. The source
version is 0.4.1, while the WordPress.org API still reports 0.4.0. Publication
requires the separate operator confirmation; no SVN upload is authorized by
this record.

The [final release record](041-final-release-record-2026-10-14.md) owns the
current package and verification evidence. Older candidate hashes below are
historical and must not be used for an upload. The 2026-10-12 text acceptance
report contains 14 cases, 14 passed and zero failed; the earlier 15/15 wording
in that section was a documentation counting error, corrected against the
retained JSON without repeating Provider calls.

## Plugin Directory State

- WordPress.org plugin URL: `https://wordpress.org/plugins/npcink-cloud-addon/`
- Plugin slug: `npcink-cloud-addon`
- WordPress.org SVN URL: `https://plugins.svn.wordpress.org/npcink-cloud-addon/`
- Historical local SVN working copy: `build/wporg-svn` (accidentally deleted 2026-10-01; recreated at r3723780 on 2026-10-02)
- Release package: `build/npcink-cloud-addon.zip`
- Stable tag: `0.4.0`

The plugin has passed WordPress.org review and was submitted to SVN. Later asset
updates were submitted separately.

Known SVN revisions:

- `r3582010`: initial WordPress.org SVN submission with trunk/tag/assets.
- `r3582534`: regenerated WordPress.org icon and banner assets.
- `r3590121`: release `0.1.1` with refreshed Cloud status UI, entitlement
  cache reuse, WordPress AI connector integration, and zh_CN metadata.
- `r3600099`: release `0.1.2` with bounded Site Knowledge Cloud transport,
  WordPress AI request-log compatibility, contract reuse documentation, and a
  clean Plugin Check release gate.
- `r3606249`: release `0.1.3` with the simplified admin overview, aligned
  quota summaries, hardened authorization redirect, refreshed screenshots,
  and updated `zh_CN` catalogs.
- `r3646532`: release `0.1.7` with the bounded runtime facade closeout,
  improved connection and Site Knowledge feedback, read-only retrieval
  acceptance projection, fixed WordPress AI localization compatibility, and
  the 33-file release package.
- `r3723719`: release `0.2.0` with release-readiness fixes: the packaged
  local permissions asset, reachable uninstall cleanup, the narrowed deprecated
  settings seam, and refreshed zh_CN catalogs.
- `r3731913`: release `0.3.0` with the connector paydown split, hardened
  alt-text handoff, the Cloud-strict observability field contract, and the
  strict Plugin Check readiness fixes.
- `r3737707`: release `0.4.0` with native exact-editor adoption feedback,
  the WordPress AI plugin 1.4.0 zh_CN compatibility shim, and the 1.4.0
  compatibility matrix slide.
- `r3652021`: release `0.1.8` with privacy-safe customer journey delivery,
  clearer monitoring disclosure, consent-gated editor quality evidence, the
  AI image extension fix, and the current 34-file release package.

## 2026-10-13 0.4.1 Candidate Status

The Addon source is prepared as a reviewable `0.4.1` candidate on merged
`master@9ae7e6a` (PR #267). The plugin header, `NPCINK_CLOUD_ADDON_VERSION`,
`readme.txt`, and bundled translation headers all agree on `0.4.1`.

The publication envelope includes the accumulated changes since published
0.4.0 source `895a606`: portal-link validation (#254), request-overhead and
monitoring persistence reductions (#250, #252, #255, #256), the two UX and
localization rounds (#258, #263), and the stale entitlement/feedback candidate
(#267). It preserves the existing public connector and Cloud contracts.

After the entitlement-refresh follow-up described below, the reproducible local package is `build/npcink-cloud-addon.zip` with SHA256:

```text
5ac5bc5287d6eb6eb2bc4d18ed1998637287c2df7e6e1f92e79f5347351594a7
```

The candidate passed the recorded local gates (`composer validate`, full tests,
PHPStan, WordPress.org and JavaScript checks, i18n checks, package release and
verification, strict Plugin Check, release verification, and `git diff --check`)
and the required CI, release-static, PHP, and WordPress AI compatibility lanes.
No `0.4.1` Git tag or WordPress.org SVN upload has been made; the directory
stable tag remains `0.4.0` until the separate publication confirmation.

The local wp-admin check on `https://magick-ai.local` confirmed the Chinese
overview, Site Knowledge recovery page, and connection-management actions.
At check time the loopback Cloud service at `127.0.0.1:18010` was unavailable
(cURL error 7), so the page correctly showed the existing recovery state:
eight dropped Site Knowledge deliveries, 782 pending updates, and 200 buffered
monitoring events. The browser did not independently display
`Not recently checked` because the recorded Site Knowledge failure takes
precedence; fresh/stale/unavailable entitlement behavior remains covered by the
automated tests.

During the 2026-10-13 recheck, the existing loopback tunnel was already live:
`/health/live` reported development `m4-preview`, backend revision
`e6e2a07f4870`, and `source_dirty=false`. No tunnel, sync, deployment, or Provider
operation was started by this task. A signed connection check returned the
Chinese success notice; an unsupported FTP URL was rejected before persistence
and produced the Chinese failure notice. Reloading consumed each notice once.
The mounted Addon was `0.4.1`, locale `zh_CN`, and the cached entitlement was
available and fresh. Site Knowledge still had 11 dropped deliveries and 782
pending updates; monitoring still had 200 buffered events. These counts are
observations, not a successful recovery claim. Recovery may invoke Cloud index
work, so it remains separate from the budget-free connection check.

Screenshots of both settings notices are retained locally in the ignored
`build/release-evidence-2026-10-13/` directory. The manual fallback still showed
two English descriptions because the site-level GlotPress language file was
dated 2026-07-02 and lacked those originals; the bundled 0.4.1 PO contains both
Chinese translations. Refresh and verify the official language pack after
publication rather than masking this installation-state gap with code changes.

WordPress.org's public plugin API was observed on 2026-10-13 reporting `0.4.0`,
last updated `2026-10-10 7:46am GMT`. This production directory observation is
the release record's non-AI evidence anchor; it does not prove live Cloud or
site behavior. The latest cited Tier B result remains the 2026-10-10 Playground
pass. Playground is not applicable to the 0.4.1 admin-only change because
bootstrap, activation, default credentials, public APIs, and compatibility
baselines are unchanged.

Remaining release work: confirm the follow-up PR complete CI rollup, obtain
the explicit SVN publication confirmation, verify the generated 0.4.1 language
pack and directory propagation after upload, and run the cross-repository matrix
only after all participating worktrees are clean. Dependabot PRs #260 (OCR
action), #261 (PHPStan), and #262 (WordPress stubs) are separate maintenance;
they are not part of this frozen candidate.

The follow-up OCR delta review (session `0ad146bd-5c15-4d2a-b369-1dfa73dceb7d`)
completed with two non-blocking low findings, both addressed and covered by the
final 15/15 JS and six-state browser verification. Earlier capped reviews and
all finding dispositions are recorded in the handoff; no unresolved blocker is
being represented as a clean zero-finding review.

A temporary SVN working copy at `/tmp/npcink-041-svn-20261013` was prepared at
repository revision `r3742751`. Both `trunk` and the locally scheduled
`tags/0.4.1` copy initially matched all 50 files of Git candidate `9ae7e6a`.
That package is superseded. The working copy was subsequently re-synced using
content checksums and matched all 50 refresh-fix package files; verify it again
against the final merged candidate before upload. No SVN commit was performed.

### Entitlement Refresh Follow-Up (2026-10-13)

OCR session `866da943-0798-4a52-8f26-c65286516ab4` reviewed
`aacce25..9ae7e6a` and returned two findings. The medium finding was valid:
a fresh automatic entitlement read left the stale global warning and Overview
badge unchanged. The isolated follow-up marks entitlement-owned nodes and
updates them only after a fresh response; recorded Site Knowledge delivery
failures stay visible. No public PHP or AJAX response contract changes.

The high finding about a missing MO update was a false positive. Direct
`gettext.GNUTranslations` inspection of the committed MO confirmed both the
short label and full stale warning are translated. The follow-up reuses existing
strings, refreshes POT/PO source references, and leaves MO bytes unchanged.

Production PHP rendering and JS were tested with explicit, process-local
fixtures: stale → fresh, failed refresh, dropped content, delivery error, error-code-only failure, and missing response state.
No persistent state or Provider calls were used. The six Chinese browser cases
passed, with no console errors or horizontal overflow at 1265×744. Screenshots,
limits, rule results, and exact isolation details are recorded in
`next-session-handoff-2026-10-13.md`. Live wp-admin was observed separately;
it still showed the recorded Site Knowledge warning. These fixtures are not
Cloud/Provider acceptance. Another chat owns the recovery-button/style changes
in the primary checkout; the user excluded those changes from this follow-up.

## 2026-10-12 zh_CN GlotPress Closeout

The logged-in Google Chrome session showed `Howdy, Npcink` (profile
`muze233`) and allowed imports as `Current`. All four Chinese (China)
default sets were verified through the live GlotPress UI on 2026-10-12:

| Translation set | Current / originals | Untranslated | Waiting | Fuzzy | Warnings |
| --- | --- | --- | --- | --- | --- |
| [Stable](https://translate.wordpress.org/projects/wp-plugins/npcink-cloud-addon/stable/zh-cn/default/) | 604 / 604 | 0 | 0 | 0 | 0 |
| [Development](https://translate.wordpress.org/projects/wp-plugins/npcink-cloud-addon/dev/zh-cn/default/) | 604 / 604 | 0 | 0 | 0 | 0 |
| [Stable Readme](https://translate.wordpress.org/projects/wp-plugins/npcink-cloud-addon/stable-readme/zh-cn/default/) | 84 / 84 | 0 | 0 | 0 | 0 |
| [Development Readme](https://translate.wordpress.org/projects/wp-plugins/npcink-cloud-addon/dev-readme/zh-cn/default/) | 84 / 84 | 0 | 0 | 0 | 0 |

Changes requested was also zero in every set. The plugin sets started at
144 current, 420 waiting, 40 untranslated, 8 fuzzy, and 1 warning; the
readme sets started at 36 current and 48 untranslated.

Operational evidence:

- Regenerated the ignored stable/dev import PO files from the merged addon
  zh_CN catalog at `3c5ed67`: 633 translated messages each, including the
  25 entries added by UX paydown PR #263. Both passed
  `msgfmt --check --statistics`.
- After the first page of 15 waiting translations was approved, stable
  reported 159 current and 405 waiting. The `Current` PO import then
  reported `442 translations were added`, with 601 current and 3 waiting.
  The two connection-default explanations were approved individually.
- The remaining artifact-format warning treated the descriptive
  `<32 lowercase hex>` placeholder as an HTML tag and expected it unchanged
  in Chinese. Verified the translated 32-character lowercase-hex meaning,
  discarded this false positive, and approved the existing translation.
  Stable then reported 604 current and zero warnings.
- The dev `Current` import reported `0 translations were added`; the live
  dev page already reflected 604 current and zero pending items after the
  stable work.
- Exported the 84-entry stable-readme PO through GlotPress, retained its
  36 existing translations, and translated the remaining 48 privacy,
  connection, Site Knowledge, and changelog entries. Both readme import
  files passed `msgfmt --check --statistics`; all HTML tags were preserved.
  The stable-readme `Current` import reported `48 translations were added`.
  The dev-readme page subsequently showed the same 84/84 completed state,
  so another upload was unnecessary.
- Local screenshots and accessibility-state evidence are in the ignored
  `build/glotpress-evidence-2026-10-12/` directory. The dev screenshot shows
  the full 604/604 count and all zero pending/warning counters. These are
  local operational evidence, not release-package contents.

Scope limits: GlotPress currently has 604 published plugin originals; the
633-entry local catalog is not evidence that unpublished source strings are
already available on WordPress.org. Recheck after the next SVN source sync.
This operation published translations only, without an SVN/plugin release.
Language-pack generation and plugin-directory propagation were not verified.
The configured local Cloud tunnel at `127.0.0.1:18010` was initially
unreachable; its later recovery and the successful provider acceptance rerun
are recorded below. B-class UX and Cloud contract work remain open.

## 2026-10-12 Provider Acceptance Rerun

Restored the existing loopback-only foreground SSH tunnel with
`pnpm run m4:preview:auto` from the Cloud repository. The canonical command
selected the LAN route and reported `tunnel_ready=true`; it did not sync or
deploy source, change containers, or edit Cloud files.

Environment and time:

- Addon source: merged `a5c289e` (0.4.0; plugin code unchanged from UX
  paydown `24ea1b5`), mounted from the main addon workspace.
- Local site: `magick-ai.local`, WordPress 7.1.1, official WordPress AI 1.4.0.
- Cloud Base URL: `http://127.0.0.1:18010`, development `m4-preview`.
- Cloud backend revision: `21d58e0d412a`, `source_dirty=false`, verified via
  `/health/live` before and after the run. Portal/frontend revision was
  `2419fdd55f819707c761b842f534d1809f753546`; it is a separate revision.
- Fresh signed capability evidence at `2026-10-12T09:29:56Z` reported text
  generation `configured`, with no provider call during that configuration
  check. Vision was configured; image generation reported `no_eligible_model`.
- Report timestamp: `2026-10-12T09:30:20Z`; completion verified by
  `2026-10-12T09:33:18Z` (17:33:18 Asia/Shanghai).

One authorized default batch was run:

```bash
composer run acceptance:wp-ai-provider
```

Exit 0: **14/14 passed**, zero failed, `evidence_state=local_verified`,
`quality_status=passed`, and `write_detected=false`
(`content_state_unchanged`). The quota preflight was available. Every case
executed, returned HTTP 200, passed the output/quality checks, and retained a
Cloud provider run ID:

| Scenario | HTTP | Quality | Provider run ID |
| --- | --- | --- | --- |
| `excerpt-default` | 200 | passed | `run_591c959f5d1340c3a4db0965effb7e76` |
| `meta-description-default` | 200 | passed | `run_e7c13a60abd54dd6984047cc9bdf9560` |
| `content-translation-en-us` | 200 | passed | `run_e1ab28981eba474882452fd73e2685c6` |
| `content-translation-blocks-en-us` | 200 | passed | `run_5cac6a3ac27749168299272a41ec36fb` |
| `summarization-short` | 200 | passed | `run_76391fe7b5044d2bb5c0c2f2bc8c3efb` |
| `slug-generation-default` | 200 | passed | `run_09e29a2df622473ea67fc40aa14a56a2` |
| `slug-generation-mixed-language` | 200 | passed | `run_5a38db899e9c4655bc30e752315ba86f` |
| `content-resizing-short` | 200 | passed | `run_0bedc6644bff487e9eac42ec3eeb557d` |
| `editorial-notes-readability-grammar` | 200 | passed | `run_3d06d77e43334d55a6f70afbc96ba468` |
| `editorial-updates-with-notes` | 200 | passed | `run_adf145b0e58e448eb5b8ee3e124e228a` |
| `classification-post-tag-existing-only` | 200 | passed | `run_1c6c9de948a54cc182dcbcfde70998b9` |
| `classification-category-existing-only` | 200 | passed | `run_9679e906ea424169aba44f1956675023` |
| `image-prompt-generation-default` | 200 | passed | `run_e9273721260e4fa88314eb0ee21f67f2` |
| `title-generation-default` | 200 | passed | `run_549165fe0c8e412ba8e067d72a11fb5d` |

The full local report is
`build/glotpress-evidence-2026-10-12/provider-acceptance.json` (ignored by Git).
The four optional comment analysis/reply, alt-text vision, and image-generation
lanes were skipped by the default runner; 14/14 does not claim those lanes or
human editorial acceptance. No repeat batch was run. The foreground tunnel
was temporary for this acceptance check; later runtime checks must verify
reachability again.

## 2026-10-10 0.4.0 Release Closeout

Version `0.4.0` was published to WordPress.org SVN from clean merged Addon
`origin/master` revision `895a606` (git tag `0.4.0`) after release PR `#247`
and matrix PR `#248` passed the protected PHP matrix, advisory
OpenCodeReview, PR body contract, and Release static gates:

```text
SVN revision: r3737707
Tag: https://plugins.svn.wordpress.org/npcink-cloud-addon/tags/0.4.0/
Local package SHA256: bc9ebf2943afb63aafbae6b9e338a9a3c3b5a0f685c9368004498eca46dd1934
```

WordPress.org API reported `version=0.4.0` (`last_updated 2026-10-10 7:46am
GMT`); the official download and the local package had different container
hashes but identical extracted file contents (recursive comparison, 50 files).

Release scope:

- native exact-editor adoption feedback `#243`: an observed main-post save
  exactly matching a retained generated-output fingerprint queues bounded,
  opt-in `cloud_agent_feedback.v1` through the existing observability
  delivery;
- the WordPress AI plugin 1.4.0 zh_CN compatibility shim `#244`: 44 new
  fixed UI translations (comment moderation value scores, Markdown feeds,
  content translation languages and editor copy, alt text settings, request
  log accessibility) and the 9 strings retired upstream with the global AI
  toggle;
- the audit rescue tooling `#245`/`#246` (developer-only, not shipped) and
  the compatibility matrix slide to AI 1.4.0 primary / 1.3.0 regression
  `#248`.

Verification evidence (all 2026-10-10, on the merged tree):

- `composer run test:all` green;
- `composer run release:verify` exit 0 (i18n audit vs the local AI 1.4.0
  install with zero fixed-UI misses, WordPress.org review guard, JS checks,
  POT freshness, package release/verify at 50 files, strict Plugin Check
  `strict result: ok`), re-run on the final merged tree with an identical
  package SHA256;
- `composer run smoke:playground` green (WordPress 7.0.4 / PHP 8.2) before
  packaging;
- compatibility lane `stable-primary` green against official WordPress AI
  1.4.0 (WP 7.0.4 / PHP 8.0, discovery + boundary checks);
- real browser Tier C `smoke:wp-ai-text-browser` green on
  `magick-ai.local` (WP 7.1.1, official AI 1.4.0, addon 0.4.0, configured
  Cloud provider through the 18010 tunnel): title/summary/rephrase
  abilities all HTTP 200, `pre_save_post_writes: 0`, one explicit save,
  revision delta 1, fixture cleaned up;
- cross-repo matrix from the toolbox:
  `composer quality:matrix:run -- --repo=npcink-cloud-addon` gates passed.

Cloud-environment gap (recorded, matching the 0.3.0 precedent):
`acceptance:wp-ai-provider` failed 0/14 with `provider_unavailable` because
the remote dev Cloud's text provider pool is unavailable at release time;
the same lane passed 15/15 at 0.3.0 closeout and the browser smoke's live
provider execution through the runtime path was green hours earlier. The
opt-in image/vision lanes remain environment-limited as recorded for 0.3.0.

Operational notes carried forward: unchanged from 0.3.0 (playground before
packaging; Plugin Check reads the packaged ZIP; resolve advisory review
threads before expecting auto-merge). The browser smoke additionally needs
`NODE_PATH` pointing at a Playwright installation when the repo has no
local node_modules. Lessons added at 0.4.0:

- A version bump fails the release-version consistency contract until the
  zh_CN PO header is synced: `composer run i18n:refresh` rewrites the POT
  `Project-Id-Version` but `wp i18n update-po` preserves the PO's old one —
  edit the line by hand and re-run `i18n:make-mo`.
- The Tier C site's dev Cloud is the SSH tunnel on `127.0.0.1:18010`
  (remote `m4-preview`), not a local docker stack; starting
  `npcink-ai-cloud` compose is unnecessary and only adds noise.
- An expired capability snapshot (browser smoke preflight
  `reason=snapshot_expired`) is refreshed out-of-band with
  `Npcink_Cloud_Entitlement_Summary::refresh( true )` through
  `wp eval`; preflight deliberately does not refresh it.
- Sliding the compatibility matrix needs the official release asset id from
  `https://api.github.com/repos/WordPress/ai/releases/tags/<version>` in
  `tests/fixtures/wp-ai-compatibility-matrix.json`, plus the matching
  `.github/workflows/ci.yml` matrix and the readiness allowlist, summary
  meta-key map, static contracts, and local test guide kept in lockstep.

## 2026-10-07 0.3.0 Release Closeout

Version `0.3.0` was published to WordPress.org SVN from clean merged Addon
`origin/master` revision `8c56aae` (git tag `0.3.0`) after release PR `#238`
passed the protected PHP matrix, advisory OpenCodeReview (v1.12.12, Chinese
comments), PR body contract, and Release static gates:

```text
SVN revision: r3731913
Tag: https://plugins.svn.wordpress.org/npcink-cloud-addon/tags/0.3.0/
Local package SHA256: ca77825c9c9ce17a336e8b4e56940d18600552665a758027705913547ec2f184
Official package SHA256: a2a23e95d290b2001c50e180bf09366c2026574ea2f6055cca8b0dad5ed9f774
```

WordPress.org API reported `version=0.3.0` after the release; the official
download and the local package had different container hashes but identical
extracted file contents (recursive comparison, 50 files).

Release scope:

- the paydown split pipeline `#220`–`#229`: runtime request guards,
  diagnostics, media payloads, media source/artifact/governance/plan
  satellites, the WordPress AI per-class split with the shared scene base,
  settings request handlers, and single-sourced descriptor validation;
- hardened alt-text handoff `#226`: fail-fast seam ordering, the
  multibyte-safe 160-byte filename bound, and non-positive attachment ID
  rejection;
- the Cloud-strict observability field contract `#237` (per-field limits
  mirrored from the Cloud schema, buffer-wide delivery bounding — the
  previously wedged local monitoring buffer drained live from 110 to 0);
- strict Plugin Check readiness `#238`: the centralized plain-text exception
  sink `throw_user_facing()` on the scene base (ratchet 142→159) and the
  site-knowledge translators comments;
- the October PHP floor annual review `#235`: the floor stays `PHP 8.0` (8.0
  at 3.96% of WordPress.org installs; 8.1 end-of-life, 8.2 support ends
  2026-12-31 and would strand 15.2% — no qualifying raise this cycle).

Verification evidence (all 2026-10-07, on the exact merged tree
`dd171e342f05bb5bca9444931b9d6f02fee39d85`):

- `composer run test:all` green, 948 assertions;
- `composer run release:verify` exit 0 with
  `WP_CLI_BIN=/opt/homebrew/bin/wp WP_DB_SOCKET=<Local socket>`: i18n audit,
  WordPress.org review guard, JS checks, POT freshness, package
  release/verify (50 files), strict Plugin Check `strict result: ok`;
- `composer run smoke:playground` green (WordPress 7.0.4 / PHP 8.2) from a
  tree without `build/` packaging output;
- Tier C on the Local MySQL site `magick-ai.local` against the local dev
  Cloud (`m4-preview` @ `b5cf4cf6`): `smoke:wp-ai-editor`,
  `smoke:wp-ai-compatibility` lane `stable-primary`, and
  `smoke:wp-ai-text-browser` (real browser, WP 7.1.1, title/summary/rephrase
  HTTP 200, zero-write review then one explicit save) all green;
  `acceptance:wp-ai-provider` 15/15 passed after the Cloud text providers
  recovered; the opt-in image-generation and alt-text vision lanes remain
  environment-limited (the local dev Cloud has no connected image provider
  and rejects vision output quality) and are recorded as addon-external
  gaps;
- cross-repo matrix from the toolbox:
  `composer quality:matrix:run -- --repo=npcink-cloud-addon` gates passed.

Non-AI evidence anchor (production observation, 2026-10-07): the WordPress.org
plugin API reported `0.2.0` live in production since 2026-10-01
(`last_updated 2026-10-01 6:45pm GMT`), and the plugin support forum had no
topics at all — no fatal reports, complaints, or unresolved issues following
the `0.2.0` publication. This observation originates from WordPress.org
production data outside the AI development loop.

Operational notes carried forward:

- run `smoke:playground` before `package:release`, or remove the generated
  `build/npcink-cloud-addon*` output first (unchanged from 0.2.0);
- `plugin-check:release` reads the packaged ZIP — rebuild the package before
  re-running it after fixes, or it reports the stale build;
- branch protection's conversation resolution blocks squash auto-merge while
  advisory OpenCodeReview findings threads are open — reply with the
  disposition and resolve the threads before expecting the merge.

Follow-up (open as of 2026-10-07): the opt-in image-generation and alt-text
vision lanes of `smoke:wp-ai-abilities` have never passed against the local
dev Cloud because no image provider is connected there and vision output is
quality-rejected. When a Cloud environment has both providers connected, run
both lanes and record the results here (see `docs/local-test-guide.md`
"WordPress AI Abilities Smoke Test: Opt-In Provider Lanes" for the
invocation and the interpretation rule).

The release kept the addon a thin Cloud connector and added no router,
prompt, preset, approval, proposal, workflow, scheduler, billing, WordPress
write, or Cloud control-plane ownership.

## 2026-10-01 0.2.0 Release Closeout

Version `0.2.0` was published to WordPress.org SVN from clean merged Addon
`origin/master` revision `5f7dff345a99ec23b652d595bcceac77e4ceb32a` after
release PR `#204` passed the protected PHP matrix, advisory OpenCodeReview,
PR body contract, and Release static gates:

```text
SVN revision: r3723719
Tag: https://plugins.svn.wordpress.org/npcink-cloud-addon/tags/0.2.0/
Local package SHA256: bce45eaa97c02aec13da61834373026bc42edf82f4e3a2e3474f9f3b623e9257
Official package SHA256: ebde5bc7067661b384ac25908ef648d809c7abcdfc0c22edf88a4102eea50f8a
```

WordPress.org API reported `version=0.2.0` after the release; the official
download and the local package had different container hashes but identical
extracted file contents (recursive comparison, 34 files).

Release scope:

- the packaged build now ships `assets/admin-permissions.js`, which the
  settings page enqueues (previous packages served a 404 for it);
- `uninstall.php` loads `Npcink_Cloud_Outbound_Policy`, so uninstall cleanup
  completes instead of fataling, and the site knowledge reconciliation cursor
  option is now removed;
- the unreachable duplicate connector runtime facade in `bootstrap.php` was
  removed (live behavior unchanged) with a static uniqueness guard;
- the deprecated `npcink_cloud_addon_get_settings()` seam no longer returns
  the stored secret;
- Plugin Check strict output is clean again (the exception-message escape
  finding from `#174` is fixed);
- readme 0.2.0 upgrade notice and fix bullets, an author URI header, AGENTS.md
  connection-endpoint documentation, and refreshed POT/PO catalogs.

Verification evidence:

- full check rollup on PR `#204` passed (PHP 8.0/8.2/8.4 contracts, WordPress
  AI lanes, Release static gates, PR body contract, advisory code review);
- `WP_CLI_BIN=/opt/homebrew/bin/wp WP_DB_SOCKET=<current Local socket>
  composer run release:verify` passed on the exact merged revision
  (test:all, i18n audit and freshness, wp.org review guard, JS checks,
  package release/verify, strict Plugin Check);
- `composer run smoke:playground` passed on the merged revision
  (WordPress 7.0.4 / PHP 8.2) from a tree without `build/` packaging output;
- the package contained exactly 34 release files.

Operational notes:

- run `smoke:playground` before `package:release`, or remove the generated
  `build/npcink-cloud-addon*` output first: Playground mounts the repository
  root and a nested packaged plugin copy breaks blueprint activation;
- the Composer default Local MySQL socket id in `composer.json` is stale on
  this machine; pass `WP_DB_SOCKET` explicitly for Plugin Check and
  release verification;
- `build/wporg-assets` was regenerated from `sj/exports/wordpress-org/`;
  `build/translate-wordpress-org` dev/stable zh_CN import files were
  regenerated from the committed `languages/npcink-cloud-addon-zh_CN.po`
  (569 translated, 1 untranslated URL msgid). The stable/dev GlotPress
  imports still need the PTE web upload; readme-set imports should follow
  once WordPress.org re-extracts the readme projects.

The release kept the addon a thin Cloud connector and added no router,
prompt, preset, approval, proposal, workflow, scheduler, billing, WordPress
write, or Cloud control-plane ownership.

## 2026-08-18 0.1.8 Release Closeout

Version `0.1.8` was published to WordPress.org SVN from clean merged Addon
`origin/master` revision `0114ff6eb0e4f2a0729f9a28cc7cf505203c2302` after
release PR `#102` passed the protected PHP matrix, PR body contract, and
release static gates:

```text
SVN revision: r3652021
Tag: https://plugins.svn.wordpress.org/npcink-cloud-addon/tags/0.1.8/
Local package SHA256: 86ff40a5c4c7d66a5c789bcfaa6b0180f79cd8be83000e6a1c41fb1baf2913d1
Official package SHA256: 733301a0ec7e9acc6fa649b015bc54def5479c57fdf034791d8644151336258e
```

Release scope:

- opt-in, metadata-only customer journey events for generation, failure
  recovery, acceptance, save, and abandonment outcomes;
- clearer monitoring scope and use disclosure, including explicit exclusions
  for content, raw WordPress user/post IDs, URLs, DOM data, and free-form error
  messages;
- monitoring-consent enforcement for editor quality evidence;
- the AI image duplicate-extension fix already merged after `0.1.7`;
- restored `0.1.7` changelog continuity in the WordPress.org readme.

Verification evidence:

- `composer validate --no-check-publish` passed;
- `composer run test:all` passed;
- `composer run smoke:playground` passed on WordPress 7.0.4 / PHP 8.2;
- `WP_CLI_BIN=/opt/homebrew/bin/wp composer run release:verify` passed on the
  exact merged `origin/master` revision;
- WordPress.org review guard, POT freshness, JavaScript syntax checks, release
  ZIP verification, and strict Plugin Check passed;
- the package contained exactly 34 release files;
- WordPress.org API reported `version=0.1.8` and the official 0.1.8 download;
- the official ZIP and the local merged-source package had different container
  hashes but identical extracted file contents.

The release kept monitoring opt-in and metadata-only. It did not add router,
prompt, preset, approval, proposal, workflow, scheduler, billing, WordPress
write, or Cloud control-plane ownership to the addon.

## 2026-08-14 0.1.7 Release Closeout

Version `0.1.7` was published to WordPress.org SVN from the clean Addon
`origin/master` release candidate after the required local gates passed:

```text
SVN revision: r3646532
Tag: https://plugins.svn.wordpress.org/npcink-cloud-addon/tags/0.1.7/
Package SHA256: 44b36245bd34d013b960816b9c0241e07e288339c493def50fd95ead19fbcd19
```

Verification evidence:

- `composer run test:all` passed;
- `composer run smoke:playground` passed on WordPress 7.0.4 / PHP 8.2;
- `WP_CLI_BIN=/opt/homebrew/bin/wp composer run release:verify` passed;
- the package contained exactly 33 release files;
- WordPress.org review guard, POT freshness, and strict Plugin Check passed.

The publication was performed through a temporary SVN working copy. The Git
working tree was not used as an SVN working copy and remains free of generated
release-state edits.

## Release Verification

Before packaging or handing off release changes, run:

```sh
composer run test:all
git diff --check
rg "/v1/runtime/workflows/runs|wp_insert_post|wp_update_post" --glob '*.php' --glob '!build/**' .
```

For full WordPress.org release verification, use:

```sh
WP_CLI_BIN=/opt/homebrew/bin/wp composer run release:verify
```

The local Plugin Check release command depends on the Local WordPress site and
the local WP-CLI/PHP paths configured in `composer.json`. On this machine,
Composer's default `/tmp/wp-cli.phar` path is not reliable, so pass
`WP_CLI_BIN=/opt/homebrew/bin/wp` explicitly for Plugin Check and release
verification commands.

## 2026-07-13 0.1.3 Release Closeout

Version `0.1.3` was published to WordPress.org SVN on 2026-07-13 after GitHub
pull request `#41` passed its required checks on exact head
`7670cdd74983752b5e90eec6e4c62de4993f2a53` and merged as
`c3ab9b0f2fb5297766afd16799a6880514a7a92f`:

```text
SVN revision: r3606249
Author: muze233
Tag: https://plugins.svn.wordpress.org/npcink-cloud-addon/tags/0.1.3/
Download: https://downloads.wordpress.org/plugin/npcink-cloud-addon.0.1.3.zip
```

WordPress.org API reported after the release:

```text
version=0.1.3
last_updated=2026-07-13 2:44pm GMT
download_link=https://downloads.wordpress.org/plugin/npcink-cloud-addon.0.1.3.zip
```

### Release Contents

- a simplified overview with compact, consistently aligned plan, point, and
  Site Knowledge quota summaries;
- Chinese quota terminology and fixed admin copy kept consistent with the
  shipped `zh_CN` catalog;
- an exact-host temporary allowlist around the validated Cloud authorization
  redirect so the flow can use `wp_safe_redirect()` without broadening the
  connector boundary;
- PHP 8.5-compatible reflection-based behavior tests while retaining PHP 8.0
  test support;
- refreshed WordPress.org screenshots for the overview, local permissions,
  Site Knowledge, and advanced troubleshooting surfaces;
- a release-version contract that keeps the plugin header, version constant,
  stable tag, POT, and `zh_CN` PO metadata synchronized.

The release does not add router, prompt, preset, proposal, approval,
workflow, scheduler, billing, runtime-truth, or WordPress-write ownership.

### Verification Evidence

The repository release gates, Plugin Check, PHP 8.0.30 and 8.5.3 behavior
suites, and a disposable WordPress 7.0.1 / PHP 8.3 package activation smoke
test passed before publication. Plugin Check returned no errors.

The package regenerated from merged `master` was:

```text
build/npcink-cloud-addon.zip
SHA256: 909222a45cc054b1091ff23bc7a241ed6e356abed957efdff6a3a0f394e25fcf
```

The downloaded WordPress.org package was:

```text
https://downloads.wordpress.org/plugin/npcink-cloud-addon.0.1.3.zip
SHA256: c17fb4e62726ae2dfc6227d540581dbee1f9c0131c0bde7c65a36e9311e557fd
```

ZIP container hashes differ because packaging metadata differs; extracted file
contents were compared recursively and matched. The four `ps.w.org` screenshot
files at revision `r3606249` also matched the Git source assets byte-for-byte.

## 2026-07-08 0.1.2 Release Closeout

Version `0.1.2` was published to WordPress.org SVN on 2026-07-08:

```text
SVN revision: r3600099
Author: muze233
Tag: https://plugins.svn.wordpress.org/npcink-cloud-addon/tags/0.1.2/
Download: https://downloads.wordpress.org/plugin/npcink-cloud-addon.0.1.2.zip
```

WordPress.org API reported after the release:

```text
version=0.1.2
last_updated=2026-07-08 9:46am GMT
download_link=https://downloads.wordpress.org/plugin/npcink-cloud-addon.0.1.2.zip
```

### Release Contents

The release deliberately kept the addon as a thin Cloud connector. The release
included:

- bounded Site Knowledge Cloud transport and runtime bridge refinements;
- WordPress AI connector request-log compatibility;
- Cloud runtime contract reuse documentation and release metadata;
- Plugin Check cleanup for an external WordPress AI feature-flag filter.

The release did not add router, prompt, preset, proposal, approval, workflow,
scheduler, billing, or WordPress write ownership.

### Plugin Check Warning Handling

Plugin Check originally reported one warning:

```text
WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
Found: wpai_feature_ai-request-logging_enabled
```

The hook name was not renamed because it is owned by the external WordPress AI
plugin feature-flag surface. Renaming it to an `npcink_cloud_addon_*` hook would
remove compatibility rather than improve safety. The accepted fix was a single
targeted PHPCS ignore on the `apply_filters()` call, with a comment explaining
that WordPress AI owns the filter name.

This is the preferred pattern for future external integration hooks:

- keep external hook names intact when the integration contract requires them;
- suppress only the exact sniff at the exact call site;
- explain the owning integration in the ignore comment;
- verify Plugin Check returns a clean result afterward.

### Verification Evidence

Before SVN commit, the following gates passed:

```sh
WP_CLI_BIN=/opt/homebrew/bin/wp composer run release:verify
git diff --check
rg "/v1/runtime/workflows/runs|wp_insert_post|wp_update_post" --glob '*.php' --glob '!build/**' .
```

Plugin Check returned:

```text
Success: Checks complete. No errors found.
```

The release package was regenerated with:

```sh
composer run package:release
```

The generated package was:

```text
build/npcink-cloud-addon.zip
SHA256: 2878cf32a66b366014629cb373ddf3ac333ebf4c99d2cceb918de5decd60d955
```

The package was inspected to confirm:

- `npcink-cloud-addon.php` version is `0.1.2`;
- `NPCINK_CLOUD_ADDON_VERSION` is `0.1.2`;
- `readme.txt` stable tag is `0.1.2`;
- no `tests/`, `scripts/`, `docs/`, `sj/`, `.git/`, `composer.json`, or other
  non-release files were included.

After SVN commit, the downloaded WordPress.org `0.1.2` zip was compared against
the local `build/npcink-cloud-addon.zip`; the contents matched.

### SVN Release Procedure Used

The release was made from a temporary SVN working copy rather than the Git
working tree:

```sh
svn checkout https://plugins.svn.wordpress.org/npcink-cloud-addon/ /tmp/npcink-cloud-addon-svn.<id>/npcink-cloud-addon
rsync -a --delete build/npcink-cloud-addon/ /tmp/npcink-cloud-addon-svn.<id>/npcink-cloud-addon/trunk/
svn add --force /tmp/npcink-cloud-addon-svn.<id>/npcink-cloud-addon/trunk
svn copy /tmp/npcink-cloud-addon-svn.<id>/npcink-cloud-addon/trunk /tmp/npcink-cloud-addon-svn.<id>/npcink-cloud-addon/tags/0.1.2
svn commit --non-interactive -m "Release 0.1.2" /tmp/npcink-cloud-addon-svn.<id>/npcink-cloud-addon
```

Before commit, both `trunk` and `tags/0.1.2` were checked for version metadata
and for accidental inclusion of non-release directories.

## WordPress.org Assets

Source images:

- `sj/source/icon-source.png`
- `sj/source/banner-source.png`

Generated WordPress.org assets:

- `sj/exports/wordpress-org/icon-128x128.png`
- `sj/exports/wordpress-org/icon-256x256.png`
- `sj/exports/wordpress-org/banner-772x250.png`
- `sj/exports/wordpress-org/banner-1544x500.png`

The same four files were copied into:

- `build/wporg-assets/`
- `build/wporg-svn/assets/`

The latest regenerated assets were committed to WordPress.org SVN as:

```text
r3582534 | Update WordPress.org icon and banner assets
```

Local Git commit:

```text
191ddb8 Update WordPress.org plugin assets
```

Screenshots were prepared earlier and are present in the WordPress.org assets
set:

- `screenshot-1.png`
- `screenshot-2.png`
- `screenshot-3.png`
- `screenshot-4.png`

## Legal and External Service Pages

The plugin discloses Npcink Cloud as an external service in `readme.txt`.

Public legal pages:

- Terms of Service: `https://cloud.npc.ink/terms/en/terms.html`
- Privacy Policy: `https://cloud.npc.ink/terms/en/privacy.html`
- Data Retention: `https://cloud.npc.ink/terms/en/data-retention.html`

Local legal site source:

- `sj/site`

The legal content was prepared with separate English and Chinese folders. English
is used for review-facing WordPress.org links; Chinese is for internal/local
reference.

Provided legal metadata:

- Company Legal Name: `麻城市牧泽世华工艺品`
- Contact email: `npc@npc.ink`
- Effective date: `2026年6月1日`
- Service domain: `https://cloud.npc.ink`

## zh_CN Translation Work

There are two separate translation surfaces:

- Plugin runtime/admin strings from PHP gettext calls.
- WordPress.org plugin directory/readme strings from GlotPress readme projects.

The in-plugin language files are:

- `languages/npcink-cloud-addon.pot`
- `languages/npcink-cloud-addon-zh_CN.po`
- `languages/npcink-cloud-addon-zh_CN.mo`

The plugin uses:

- Text domain: `npcink-cloud-addon`
- Domain path: `/languages`

Do not add `load_plugin_textdomain()` unless the existing project contract
changes; current tests assert the text domain/domain path behavior and absence
of explicit `load_plugin_textdomain()`.

Local translation refresh commands:

```sh
WP_CLI_BIN=/opt/homebrew/Cellar/wp-cli/2.12.0/bin/wp composer run i18n:pot
WP_CLI_BIN=/opt/homebrew/Cellar/wp-cli/2.12.0/bin/wp composer run i18n:update-po
WP_CLI_BIN=/opt/homebrew/Cellar/wp-cli/2.12.0/bin/wp composer run i18n:make-mo
```

Note: `/tmp/wp-cli.phar` was broken in this environment during the translation
work, so the Homebrew WP-CLI binary was used explicitly.

Local Git commit:

```text
5c886f5 Update Chinese translations
```

## WordPress.org Translation Imports

Generated import files are in:

- `build/translate-wordpress-org/npcink-cloud-addon-stable-zh_CN.po`
- `build/translate-wordpress-org/npcink-cloud-addon-dev-zh_CN.po`
- `build/translate-wordpress-org/npcink-cloud-addon-stable-readme-zh_CN.po`
- `build/translate-wordpress-org/npcink-cloud-addon-dev-readme-zh_CN.po`
- `build/translate-wordpress-org/npcink-cloud-addon-zh_CN-wordpress-org-imports.zip`

These are ignored by Git because `build/` is ignored. Regenerate them when
source strings change.

Import status confirmed on 2026-06-23:

- `Stable Readme`: 46 waiting translations.
- `Stable`: 217 waiting translations.
- `Development Readme`: 46 waiting translations.
- `Development`: 217 waiting translations.

Total zh_CN waiting/fuzzy count:

```text
526
```

This total matches the four uploaded files:

```text
46 + 217 + 46 + 217 = 526
```

Important interpretation:

- `Waiting` means uploaded successfully but not approved.
- WordPress.org project percentages still show `0%` until translations become
  `Current`.
- The Chinese plugin directory page will not show the translations until the
  `Stable Readme` strings are approved.

## GlotPress Import URLs

Use these URLs when uploading refreshed PO files:

- Stable Readme:
  `https://translate.wordpress.org/projects/wp-plugins/npcink-cloud-addon/stable-readme/zh-cn/default/import-translations/`
- Stable:
  `https://translate.wordpress.org/projects/wp-plugins/npcink-cloud-addon/stable/zh-cn/default/import-translations/`
- Development Readme:
  `https://translate.wordpress.org/projects/wp-plugins/npcink-cloud-addon/dev-readme/zh-cn/default/import-translations/`
- Development:
  `https://translate.wordpress.org/projects/wp-plugins/npcink-cloud-addon/dev/zh-cn/default/import-translations/`

Use format:

```text
Portable Object Message Catalog (.po/.pot)
```

If GlotPress offers an import status option and the account is not already a PTE,
use `Waiting`.

## PTE Follow-Up History

PTE means Project Translation Editor. PTE access was requested for:

- Plugin: `npcink-cloud-addon`
- Locale: `Chinese (China) / zh_CN`
- WordPress.org username: `muze233`

The 2026-07-02 closeout below records the approval and completion state. Keep
the request template in this section as historical context only.

## 2026-07-02 zh_CN PTE Closeout

On 2026-07-02, the WordPress.org Polyglots notification confirmed that
`muze233` was added as a Project Translation Editor for:

```text
Plugins -> Npcink Cloud Addon
Locale: Chinese (China) / zh_CN
Project: https://translate.wordpress.org/projects/wp-plugins/npcink-cloud-addon
Granted by: shenyanzhi
```

Interpretation:

- PTE access allows approving and importing translations for this plugin's
  zh_CN translation sets.
- It does not change this plugin's product boundary or runtime behavior.
- WordPress.org addon translation work should stay scoped to the
  `npcink-cloud-addon` text domain and the plugin readme projects.
- The separate local WordPress AI plugin compatibility shim remains a bounded,
  admin-only `ai` text-domain shim for fixed UI strings only. Dynamic ability
  metadata belongs in the plugin that registers the ability.

### Local Preparation

Before the online submission, the local zh_CN language file was checked:

```sh
msgfmt --statistics -o /tmp/npcink-cloud-addon-translation-submit/check.mo \
  /tmp/npcink-cloud-addon-translation-submit/npcink-cloud-addon-zh_CN-glotpress-import.po
```

Result:

```text
435 translated messages.
```

The temporary GlotPress import source was:

```text
/tmp/npcink-cloud-addon-translation-submit/npcink-cloud-addon-zh_CN-glotpress-import.po
```

Do not commit that temporary file. It is only an upload artifact derived from the
local `languages/npcink-cloud-addon-zh_CN.po`.

### Online Submission

The Development default translation set was opened at:

```text
https://translate.wordpress.org/projects/wp-plugins/npcink-cloud-addon/dev/zh-cn/default/
```

The import form was submitted with:

```text
Format: Auto Detect
Status: Current
File: npcink-cloud-addon-zh_CN-glotpress-import.po
```

GlotPress reported:

```text
264 translations were added
```

After import, two remaining untranslated fixed UI strings were manually added:

| Original | Translation |
| --- | --- |
| `Entitlement and package are read from Cloud into a short local summary cache. Re-verifying refreshes the latest Cloud summary.` | `权益和套餐会从 Cloud 读取到简短的本地摘要缓存中。重新验证会刷新最新的 Cloud 摘要。` |
| `Details` | `详情` |

Three waiting fixed UI translations were reviewed and approved:

| Original | Translation |
| --- | --- |
| `not returned by Cloud` | `Cloud 未返回` |
| `Advanced Information` | `高级信息` |
| `Advanced` | `高级` |

One GlotPress warning was resolved by changing the first translation above to:

```text
未从 Cloud 返回
```

The warning was only about the original string starting with lowercase `not`
while the previous translation started with uppercase `Cloud`; it was not a
placeholder or format-string error.

### Readme Completion

The plugin readme translation sets are separate from the PHP gettext/default
translation sets. Do not upload the addon PO file into readme projects.

Development Readme had one untranslated changelog string:

| Original | Translation |
| --- | --- |
| `Refresh Cloud connection status actions, entitlement summary caching, WordPress AI connector integration, zh_CN strings, and release packaging checks.` | `更新 Cloud 连接状态操作、权益摘要缓存、WordPress AI 连接器集成、zh_CN 字符串和发布打包检查。` |

After this was saved, Stable Readme synchronized to the same completed state.

### Final WordPress.org State

The final project overview row for zh_CN was:

```text
Chinese (China)    100%    100%    100%    100%    0
```

Meaning:

- Development: 100%
- Development Readme: 100%
- Stable: 100%
- Stable Readme: 100%
- Waiting/Fuzzy: 0
- Untranslated: 0
- Warnings: 0

The verified GlotPress pages were:

- `https://translate.wordpress.org/projects/wp-plugins/npcink-cloud-addon/dev/zh-cn/default/`
- `https://translate.wordpress.org/projects/wp-plugins/npcink-cloud-addon/stable/zh-cn/default/`
- `https://translate.wordpress.org/projects/wp-plugins/npcink-cloud-addon/dev-readme/zh-cn/default/`
- `https://translate.wordpress.org/projects/wp-plugins/npcink-cloud-addon/stable-readme/zh-cn/default/`
- `https://translate.wordpress.org/projects/wp-plugins/npcink-cloud-addon/`

### Directory Page Behavior

The global WordPress.org plugin page remains the English directory page:

```text
https://wordpress.org/plugins/npcink-cloud-addon/
```

The Chinese-localized directory page is:

```text
https://cn.wordpress.org/plugins/npcink-cloud-addon/
```

After GlotPress reaches 100%, the remaining work is to wait for WordPress.org to
generate and distribute language packs and for plugin-directory caches to
refresh. If the Chinese page still shows English after caches settle, check the
Stable Readme translation set first, because the localized plugin directory page
uses readme translations rather than the runtime `.po` file alone.

For local language-pack verification after generation, use the local WordPress
site:

```sh
wp language plugin update npcink-cloud-addon \
  --path="/Users/muze/Local Sites/npcink/app/public"
```

or use the WordPress admin updates screen to refresh translations.

### Follow-Up

- Keep WordPress.org translations at 100% when new release strings are added.
- Re-run local i18n refresh before the next release package.
- Keep the AI plugin compatibility shim audit separate from WordPress.org addon
  translation work.
- If the WordPress.org plugin directory does not localize after the language
  pack/cache window, verify Stable Readme first, then the generated language
  pack state.

Suggested request title:

```text
PTE Request for Npcink Cloud Addon
```

Suggested request body:

```text
Hello Polyglots team,

I am the plugin author of Npcink Cloud Addon:
https://wordpress.org/plugins/npcink-cloud-addon/

I have imported Chinese (China) translations for the following projects:

- Stable
- Stable Readme
- Development
- Development Readme

There are currently 526 zh_CN strings waiting for review.

I would like to request Project Translation Editor access for this plugin for
#zh_CN, so I can review and maintain the Chinese translations.

WordPress.org username: muze233

Thank you.
```

Request location:

```text
https://make.wordpress.org/polyglots/
```

## Browser Automation Note

Chrome extension automation was not available during the 2026-07-02 closeout,
but the logged-in Playwright browser session could access GlotPress as
`Howdy, Npcink` and submit file inputs. The Development default PO import,
manual string saves, waiting approvals, warning fix, and readme completion were
all completed through that browser session.

Do not assume future upload failures are file-format issues. Validate PO files
locally with `msgfmt --check --statistics` or `msgfmt --statistics` before
debugging GlotPress/browser behavior.
