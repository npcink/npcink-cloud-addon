# 分支治理与 dependabot 合并收口

状态：Accepted（2026-10-05）

适用范围：`npcink-cloud-addon` 的分支生命周期治理、dependabot 依赖
PR 的合并流程、`pr-body-contract` 必需检查与机器人 PR 的交互。

本文记录 2026-10-05 仓库维护阶段的结论与工程规则。此前的静态分析与
本地工具收口见
`docs/static-analysis-and-local-tooling-closeout-2026-10-04.md`，
设置页拆分记录在 `docs/cloud-addon-complexity-budget.md`。

## 1. 阶段结论

- 历史分支清理：31 个远端、19 个本地"PR 已合并"分支全部删除；
  第二个 worktree（`sync-cloud-addon`，分支已合并且工作区干净）随
  分支一并移除。清理前逐分支核对 PR 合并状态，只删已合并者。
- 两条无 PR 记录的本地分支按内容判定处置：
  `codex/acceptance-contract-provenance` 经 `git cherry` 证明补丁已
  全量存在于 master，直接删除；`codex/wp-ai-localization-fix` 与
  master 无补丁等价、含真实未吸收内容，但属于被放弃的平行实现线
  （其主题经 #202–#208 的 factual-fixtures 重设计另行落地），打归档
  tag `archive/wp-ai-localization-fix-20260928` 后删除分支。
- dependabot 三个依赖 PR（github-script 7→9、open-code-review
  1.12.11、upload-artifact 4→7）全部 squash 合并，合并前核实仓库
  脚本未使用 github-script v9 中被移除的 `require('@actions/github')`
  模式。

## 2. dependabot PR 合并的三层阻塞（本次的核心发现）

这三个 PR 长期挂着的根本原因不是依赖风险，而是三层流程阻塞：

1. **正文契约失败**：`pr-body-contract` 是必需检查，dependabot 的
   默认正文没有 Scope/Boundary/Verification/Risk 结构，必然失败。
   处置：维护者为依赖类 PR 补写合规正文（`gh pr edit`）。
2. **落后于 master 且重定基会覆盖正文**：`@dependabot rebase`
   重定基后会用自己的模板**重写 PR 正文**，把补好的合规正文冲掉，
   正文检查再次失败。处置：改用**手动 rebase 后 force-with-lease
   推送**——维护者推送只触发 `synchronize`、不触碰正文；若
   dependabot 已自行重定基，直接在其新 head 上重贴正文即可。
3. **评审线程阻塞**：advisory code-review 在新 head 上留下行内
   评论线程，未解决会话使 mergeState 停在 BLOCKED（即使全部必需
   检查绿、mergeable=MERGEABLE）。处置：合并前用 GraphQL
   `resolveReviewThread` 清线程；这个检查应成为合并任何带机器人
   评审 PR 的固定收尾步骤。

## 3. 工程规则

1. 删除任何分支前先取得机器证据：`gh pr list --state all` 核对
   PR 合并状态；无 PR 记录的分支用 `git cherry master <branch>`
   判断补丁是否已被上游吸收（全 `-` 才可直接删）。
2. 无 PR 且含未吸收内容的"平行实现线"分支：不直接删除，打
   `archive/<名称>-<日期>` tag 并推送远端后删除分支。tag 不占
   分支命名空间且永久可查（仓库已有 `pre-refactor-*`、`0.2.0`
   等 tag 惯例）。
3. dependabot 依赖 PR 的合并顺序：先核实破坏性变更是否影响本仓库
   （例如 v9 的 ESM 迁移），再按第 2 节三层步骤处理；合并会移动
   master，多 PR 逐个"rebase → 正文 → 线程 → 合并"串行推进。
4. 判定"检查全绿但 BLOCKED"时，先看未解决评审线程，再确认必需
   检查在新 head 上有最新 success 结论；`check-runs` 列表里的
   历史 failure 记录不代表当前状态，以 mergeStateStatus 为准。
5. dependabot 豁免（2026-10-06 已实施）：`pr-body-contract.yml` 对
   `dependabot/*` 分支在步骤内早退并报告 success（必需检查不缺席），
   消除第 2 节第 1 层阻塞；维护者合并 dependabot PR 只需处理重定基
   与评审线程两层。

## 4. 遗留事项

- 2026-10-06 落地：`pr-body-contract.yml` 已对 `dependabot/*` 分支加入
  豁免（作业仍运行并报告 success，必需检查不缺席），第 2 节第 1 层
  阻塞消除；维护者只需处理第 2、3 层。
- 归档 tag `archive/wp-ai-localization-fix-20260928` 中的翻译块
  级隐私断言意图，若未来强化请求日志隐私测试可作参考。
