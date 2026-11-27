# Verification Evidence Standard

Status: active standard for `npcink-cloud-addon`.

Purpose: state which evidence tier each change type owes and how long
evidence stays valid, so release readiness never depends on memory or on the
silent reuse of stale results. This standard complements
`cloud-addon-complexity-budget.md` (which defines the tiers) and `AGENTS.md`
(which defines the gates). It adds no boundary and no product control.

## Local symlink and PHP worker identity

After changing a Local site's plugin symlink, WP-CLI reflection and a successful
Cloud request are insufficient proof of the web installation. A long-lived
PHP-FPM worker can retain the previous resolved plugin path even while a new CLI
process loads the new target. Record the mounted target, then inspect a fresh
authenticated editor page's actual asset URLs, HTTP status and visible translated
controls. Require the localization and notice-action scripts to load from the
canonical plugin URL, with the current localized payload and expected links.
Do not call this browser cache or require a hard refresh until the web response
has been checked. If web and CLI identities differ, gracefully reload only the
named Local site's PHP service and verify again. Never restart another site or
production as part of this local recovery. Preserve unsaved editor state before
refreshing a user's existing tab. No paid AI request is needed to check loading.

2026-11-17 local incident: CLI loaded the intended Addon, but fresh editor HTML
still referenced the former worktree through an invalid `/wp-content/plugins/Users/`
asset URL (404), with old failure copy and no notice-action script. Reloading the
`magick-ai` PHP-FPM service restored canonical asset URLs (both scripts HTTP 200),
Chinese title/excerpt/summary/editorial controls, and the current troubleshooting
message/link payload. The user tab was refreshed only after Save was confirmed
disabled. This proves local loading recovery; it does not prove generation success.

## Evidence Tiers

Native WordPress AI upgrades also require checking the installed Ability's
message roles against the adapter: source, additional context and system
instruction are separate contracts. A former instruction-only message may now
contain the article. Cover every action of a shared family (shorten, expand,
rephrase), a source longer than the instruction cap, preservation of HTML and
links, exact response consumption, and unchanged article/meta fingerprints.
Real WordPress with intercepted HTTP is local integration evidence, not hosted
model quality or browser adoption. Keep unsupported scene mappings explicit;
do not infer support from enabled upstream switches or old acceptance reports.

The tiers keep the meaning they have in `cloud-addon-complexity-budget.md`:

- Tier A — deterministic: `composer run test:all` (lint, contract tests,
  boundary search), `composer run stan`, `composer run check:wporg`, and
  `composer run check:js` when JavaScript changed.
- Tier B — disposable compatibility: `composer run smoke:playground`.
- Tier C — real integration: Local MySQL/Cloud smokes, browser smokes, and
  the acceptance suites (`composer run smoke:wp-ai-*`,
  `composer run acceptance:wp-ai-provider[:quality]`,
  `composer run local:journey:inspect`), each recorded with date and
  environment.
- Tier D — release and production: `composer run release:verify`, the
  WordPress.org release gate, and post-release production observation.

## Change-Type Matrix

| Change | Tier A | Tier B | Tier C |
| --- | --- | --- | --- |
| Any `includes/`, `assets/`, plugin main-file, or `uninstall.php` change | required | required when bootstrap, activation, the public connector API, the default credential/connector state, or the WordPress/PHP compatibility baseline is affected | required for the touched surface: transport and signing changes owe the media and WordPress AI behavior suites plus a Local Cloud smoke; settings and admin flows owe a browser smoke of the changed flow; the WordPress AI provider surface owes provider acceptance |
| `tests/` only | required | not applicable — record why | not applicable — record why |
| `docs/` only | required (static contracts read docs) | not applicable — record why | not applicable — record why |
| CI job or `scripts/` gate change | required | as triggered by scope | observe one complete check rollup on the PR before merge |
| Composer platform, PHP floor, or WordPress baseline change | required | required | re-run the WordPress AI compatibility lanes and record the rollup |
| Exception-message construction or new placeholder-bearing translatable strings | required, plus a strict Plugin Check spot run: `composer run package:release && composer run plugin-check:release` on the branch (rebuild first — the check reads the packaged ZIP, so a stale `build/` reports already-fixed findings) | not applicable — record why | not applicable — record why |

"Not applicable — record why" keeps the existing PR-template discipline: the
reason is written into the PR's verification record, never silently omitted.

For an admin browser fixture, assert the intended state after navigation before
measuring any control. Similar markup does not prove the connected path. Seed
disposable settings through the current storage contract and derive cache keys
from the normalized scope. Record which responses came from WordPress, which
were intercepted or synthesized, and which real Cloud paths remain untested.
Keep the source revision, locale, and viewport with the evidence.

Both 0.3.0 Plugin Check regressions — unescaped dynamic exception text
(introduced by the localized connector failures) and placeholder strings
without `translators:` comments — landed with every per-PR gate green and
surfaced only when `release:verify` first ran at release time. The spot-run
row exists so that class is caught at change time, not at the next release.

## Reconciliation And Closeout Evidence

For Site Knowledge query changes, use `composer run smoke:site-knowledge-mysql`
with Docker, native PHP `mysqli`, and WP-CLI. `Release static gates` runs it on
a disposable WordPress install and MySQL 8.4 database. It shares the fixture
with `smoke:playground` (SQLite), verifies the real `$wpdb` query and exact
same-second page boundary, and does not exercise delivery, Cloud, or WP-Cron.

Keep execution marks, scan-cursor progress, durable buffer retention, delivery
acceptance, and Cloud documents/chunks as separate evidence. A recent delivery
clock is not a scan-coverage watermark. A merged fix is not proof that the
site loaded it or that missing documents recovered.

For PHP changes that move gettext source references, run
`composer run i18n:check` before publishing even when no message was added.
If stale, refresh the catalogs and confirm that reference-only changes preserve
message mappings; re-verify the package when its files changed. Inspect the
complete final-head check rollup, including relevant advisory checks, after
any correction.

Record a budget/timeout-limited OCR run as incomplete, with selected/completed
items and its exact range. A docs-only selection skip does not fill historical
model-review gaps. Preserve raw evidence outside disposable worktrees before
archiving them, verify squash-merged content before branch deletion, and keep
a site-linked checkout update separate from Git cleanup.

See the [2026-10-17 reconciliation retrospective](site-knowledge-reconciliation-retrospective-and-handoff-2026-10-17.md)
for the dated case and remaining recovery acceptance.

### Customer Journey: natural delivery and Local backlog recovery

1. Confirm the Local site's mounted Addon and Web worker identity, the intended
   development Cloud, monitoring consent, and Cloud health. Inspect the existing
   queue with `composer run local:journey:inspect`. Keep a timestamped baseline
   of event hashes and the expected journey steps outside the Git worktree.
2. Use ordinary hourly WP-Cron for acceptance. For a blocked-batch fix, observe
   an unassisted run that rejects a confirmed invalid reference and still
   delivers a valid event from the same backlog. When diagnosing a transport
   outage, verify unconfirmed events stay queued; do not classify connection
   errors as invalid references. A healthy endpoint alone is not evidence of
   successful delivery.
3. After natural acceptance, an operator may authorize faster backlog recovery
   for that Local development site only. From its verified WP-CLI context, run
   only `wp cron event run npcink_cloud_addon_flush_observability`, one round at
   a time and away from a due natural run. This is a shared observability hook:
   confirm its other buffered uploads are in scope before running it. After
   each round check Cloud health, queue progress, acknowledgements and explicit
   rejections. Stop on rate limits, connectivity or unexpected errors, or a
   nonempty queue with no progress. Resume only after the cause clears, with
   pacing appropriate to the active rate limit. Do not bypass the hook, delete
   queued events, alter limits, or add a faster recurring schedule. Recheck the
   next due time and unchanged hourly recurrence; a manual run may move the
   next due time.
4. Reconcile the baseline read-only against the same authenticated site's Cloud
   records. Distinguish stored events, confirmed single-event rejections absent
   from Cloud, and anything unresolved. Verify the expected editor steps for
   the original Run without rerunning generation or resaving content. Queue
   zero is not a delivery receipt. Commit only aggregate evidence; retain
   event hashes, Run references and other site-specific evidence privately.

**2026-11-24 Local-to-M4 development case (Addon #365):** Web PHP-FPM was
still loading the previous Addon after WP-CLI showed the intended version.
Reloading only the Local site's PHP service and checking fresh Web assets
established the loaded version. A separate M4 Docker disk-full failure reset
connections; 152 queued events were retained. After the host operator restored
disk capacity and Docker health without replacing volumes, a natural run
delivered one valid event and rejected one invalid reference. A subsequent
manual drain stopped on a short-window rate limit and resumed at a slower
pace; that interval was not adopted as a site setting. Final read-only
reconciliation of the 171 baseline events found 138 stored and 33 confirmed
invalid and absent; the queue was empty and the expected title generation,
acceptance and save steps matched. No new model invocation or editor write
was needed. This closes the development transport incident, not production
acceptance or evidence of user value.

## 跨系统回执与中断恢复验收

变更“远端接受 → 本地保存回执/释放 owner → 后续推进”路径，或修复该路径卡住时，
须分开验证远端执行、本地回执持久化、后续操作推进和最终数据状态。HTTP 成功、
现场人工恢复、提示改进及工单预填都不替代防复发验收。

评审须检查保存/CAS 返回值；失败不得报告完整成功。冲突重试必须有限，重新读取
权威状态并处理请求内缓存，仅合并当前 owner 拥有的字段，保留并发意图。恢复身份
须先持久化，跨 PHP 请求/进程可达；不能只说“有幂等 key”，却让本地锁挡住恢复。

| 故障边界 | 必须证明的行为 |
| --- | --- |
| 远端已接受，本地回执保存冲突 | 同一远端执行的回执最终保存，并发意图保留；重试耗尽显式失败且保留恢复身份 |
| 接受后、本地 finalizer 前进程退出 | 新进程只靠持久身份恢复；不依赖内存响应，不制造新的执行/用量 |
| 已知未发送，释放 owner 冲突/耗尽 | 后续可恢复；若取消旧 sender，证明其恢复后无法再通过发送前原子检查 |
| 原请求延迟、running、未知/缺失/过期记录、错误终态 | 保守阻断；超时、404、HTTP 错误和普通 failed 标签不是旧 writer 已结束的证明 |
| 回执来自不同站点、凭据命名空间或请求身份 | 拒绝解锁；保持原 payload、抑制和并发意图 |
| 权威匹配终态随后可用 | 不只证明拒绝；还须证明回执保存、owner 解除及原操作继续推进，并核对最终 presence/撤回条件 |

取消安全须给出发送检查/原子状态变更的线性化依据；lease 到期本身不足够。
涉及并发或重启正确性的修改，除确定性回归外须给出真实数据库独立连接与 WordPress
缓存证据，以及真实进程退出后新进程恢复证据。若远端用 double，必须标明，并把
真实签名网络读取/执行证据另记。尚无安全恢复证明的窗口应明确保留，不得为跑绿清锁。
不同路径按实际共享入口和持久化语义决定覆盖，不要求无关模块全量复测，也不得把
源码横向检查写成逐入口真实 E2E 或全仓可靠性证明。

收尾记录分别列出现场恢复、防复发代码、确定性回归、真实 DB/cache、进程、签名网络、
CI、M4 candidate/accepted、合并、生产及人工验收；未完成项保留退出条件。故障模型、
源码与回归入口须入库，原始证据不能只留在可清理工作树。案例及当前候选限制见
[2026-11-16 回执恢复复盘](site-knowledge-receipt-recovery-retrospective-2026-11-16.md)。

## Evidence Freshness
- Tier C evidence expires when the surface it covers changes, or after 30
  days, whichever comes first. A release must not cite expired evidence.
- A release record must cite, with dates: the latest Tier B playground
  result, the latest Tier C result for each touched surface, and the
  production observation point that followed the previous release.

## Non-AI Evidence Anchor

This repository is developed AI-first: code is AI-written and reviewed by an
advisory AI gate. To keep that loop honest, every published release must
include at least one evidence item produced outside the loop — a human-run
acceptance record, a production observation, or a verified user report —
with its date. A release supported only by AI-written code plus AI-advisory
review is not releasable, even when every automated gate is green.

## Relation To Existing Rules

- `AGENTS.md` stays the gate authority; this standard only classifies which
  tiers a change owes and when evidence expires.
- `cloud-addon-complexity-budget.md` keeps the tier definitions, including
  the rule that a passing playground run is never a substitute for a higher
  tier.

## Reversible-Pause and Localization Release Evidence

For a feature pause, verify both the host entry-point filter and every affected
transport path, including registered aliases and legacy calls. Assert refusal
before signing/outbound HTTP and retain positive generic-task coverage. Preserve
the host enable option and historical data; verify hidden configuration survives
an ordinary settings save. Do not classify a task by words in its content.

Fixed UI localization requires actual runtime coverage: inspect the installed
host/addon versions, mounted addon path, PHP strings, the loaded minified editor
asset and any bounded REST error projection. A dictionary mapping alone is not
proof that the browser or REST response uses it. Keep dynamic ability metadata
outside addon localization ownership. Audit, package and browser evidence must
refer to the same shipping bytes; a changed package invalidates that identity
claim. Keep unrelated localization repairs independently reviewed and backport
release-only fixes to master so the next ordinary build retains them.

A published-baseline release candidate and master can intentionally differ.
Record both identities and their scope; do not retarget an old candidate tree
into master to close a PR. A fixture downgrade/restore proves preservation in
that fixture only, not every production site's backup or recovery readiness.
Before distribution, refresh the evidence required above, identify target sites
and their backups, retain an original rollback ZIP and satisfy the non-AI anchor.
Never uninstall the plugin or erase options/history as a rollback shortcut.

The 2026-10-19 pause/localization closeout merged master PRs
[#289](https://github.com/npcink/npcink-cloud-addon/pull/289),
[#290](https://github.com/npcink/npcink-cloud-addon/pull/290) and
[#293](https://github.com/npcink/npcink-cloud-addon/pull/293).
[#292](https://github.com/npcink/npcink-cloud-addon/pull/292) separately merged
into the isolated combination candidate, not master. Fixed-UI auditing reached
zero gaps and the exact combined ZIP was browser-checked in a disposable
WordPress fixture. Plugin distribution and production-site acceptance remain
separate; source merge and CI do not grant either permission.

## 媒体源同步与恢复验收

变更删除、补偿、重试或 Cron 时，至少覆盖：正常新增／修改／删除、严格漏通知、
有效项保留、许可关闭、失败后恢复。先本地复现，再在获授权站点用最少临时样本
验收；默认不添加产品入口或永久运维配置。

分别记录事件捕获、缓冲持久化、下一次唤醒、请求接受、Cloud 运行结果、精确文档／
分块存在性以及撤回 completed。HTTP 200、索引暂空、任务被人工标失败，均不能
替代其余状态。核对旧写入并保留未核实退出，不能为验收清空安全标记。

故障注入和到期提前必须标注；至少一次真实 Cron 回调证明冷却不被绕过及到期补投，
不能声称已经等待自然一小时或验证长期稳定。证明去重身份、文件指纹与实际加载
文件一致。源码、包、本机、M4、测试站、生产部署和人工验收保持分别记账。

离开环境前，核对测试图片文件／行及云端文档／块均已移除，撤回已完成，原内容和
设置已恢复，绑定未丢失；再撤临时 Cron、保护代码、标记和注入状态。把证据移出
临时 worktree，链接到仓库复盘和活动文档后才清理分支。主机故障的恢复与根因
结论分开，未复现的对照不能授权永久调参或关闭独立调查。

实例与限制见[2026-11-12 复盘](media-source-sync-retrospective-2026-11-12.md)。

## Native editorial-update save consumer (2026-11-17)

A suggestion-only Ability response does not prove that the upstream editor
saved it correctly. Native `savePost()` can resolve without persistence; verify
its failure state and saved content before completing Notes. Test unsaved drafts,
concurrent typing, partial block failure and failed Note completion in the owning
AI plugin. Keep these write controls out of the Addon; its fixed UI translations
may explain the native save status and point to Notes/revisions.

The AI 1.5.0 local source patch, rebuild/rollback and six browser scenarios are
recorded in Cloud `docs/history/engineering/2026/wordpress-editorial-save-correctness-2026-11-17.md`.
This does not certify the full AI 1.5 translation inventory: the installed version
has 180 pre-existing strict-UI gaps, unchanged by the editorial patch. New fixed
recovery notices require their own Chinese behavior coverage and real rendering.
