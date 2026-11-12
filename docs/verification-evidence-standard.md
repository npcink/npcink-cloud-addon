# Verification Evidence Standard

Status: active standard for `npcink-cloud-addon`.

Purpose: state which evidence tier each change type owes and how long
evidence stays valid, so release readiness never depends on memory or on the
silent reuse of stale results. This standard complements
`cloud-addon-complexity-budget.md` (which defines the tiers) and `AGENTS.md`
(which defines the gates). It adds no boundary and no product control.

## Evidence Tiers

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

Both 0.3.0 Plugin Check regressions — unescaped dynamic exception text
(introduced by the localized connector failures) and placeholder strings
without `translators:` comments — landed with every per-PR gate green and
surfaced only when `release:verify` first ran at release time. The spot-run
row exists so that class is caught at change time, not at the next release.

## Reconciliation And Closeout Evidence

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
