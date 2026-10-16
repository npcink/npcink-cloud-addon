# 当前状态真相记录（草案）— 2026-10-16

状态：供交接评审的带时间戳汇总；不新增运行时契约或改写历史验收。
日期按 Asia/Shanghai；保留证据中的 UTC 原始时间。

## 1. 使用方式与证据优先级

本记录区分本次核对、历史实测和待决事项。它承接
[旧会话交接](next-session-handoff-2026-10-16.md)的当前待办入口，
保留其中的历史过程；正式规则仍以各 owner 的活跃标准/契约为准。
Cloud owner 待答问题见 [决策包草案](cloud-owner-decision-package-draft-2026-10-16.md)。

引用本记录前先看证据时间、环境、source/index revision 和观察层：

1. 对同一对象的后续验证覆盖较早待办。例如 10 月 16 日发布/语言包记录
   覆盖 10 月 14–15 日“等待 SVN、等待语言包”的描述。
2. 代码是否已合并，以当前源文件/合并记录为据；候选的离线验证不代表已上线。
3. Cloud 原始输出、Addon 归一化投影、本地 UI 和人工检索分别记录。
   某层的 fallback 不能当作上游字段或后台任务存在的证明。
4. [验证证据标准](verification-evidence-standard.md)的 Tier C 有效期为
   30 天或对应 surface 变化前，以先到者为准。日期未过期也不代表当前环境未变化。

## 2. 源码、工作区与只读审计快照

下表为初稿整理阶段、文档编辑前的观察，非 fetch 后的远程最新性保证；
该阶段未切换任何仓库分支。后续提交/PR 的 revision 与检查结果另记在 PR 中，
不倒改本表的历史采样值。

| 仓库 | 观察到的 HEAD | 工作区状态 |
| --- | --- | --- |
| Addon | `f0e2a5854bbe373e45240b23b5fce30b00df57fa`，`master...origin/master` | 本次开始时干净；随后仅本任务文档改动 |
| Toolbox | `569da58106c877fadb65316e4375a8bd3d1f6b1e`，`master...origin/master` | 有既有文档改动：`docs/session-handoff-2026-10-16-internal-link-related-content-history.md`；本任务未修改 |
| AI Cloud | `fe9207965f4a5049f80736019114f360186ec96b`，`master...origin/master` | 本次只读核对时干净 |

本次 `composer quality:matrix` 于 `2026-10-16T12:08:16Z`
（北京时间 20:08:16）生成，只做状态快照：gates `not_run`，M4 runtime
acceptance `not_requested`。Core 默认工作区为
`codex/docs-rc-audit-refresh-2026-10-16`，Adapter 为
`codex/m4-pgy-stack-rc-docs-2026-10-15`；不得因工作区干净就称为七仓主分支验收。

历史完整矩阵时间为 `2026-10-16T11:14:49Z`，文档记录 7/7 gates 通过、
M4 `not_requested`，来源为
[发布日志的 cross-repository closeout check](wordpress-org-release-and-translation-log.md#2026-10-16-cross-repository-closeout-check)。
这是当次运行证据，不能替代本次 HEAD 的完整测试或新的 M4 acceptance。

### 10 月 16 日 20:29 只读审计更新

以下为 `2026-10-16T12:29:20Z–12:29:28Z`（北京时间 20:29:20–20:29:28）的
保留采样；本次发布文档没有重查 Cloud 或触发恢复。
Cloud 数据库时间 `12:29:23.683958Z`，WordPress 前后清单时间 `12:29:20Z`、
`12:29:27Z`，前后公开清单和修改时间一致。

- M4 开发栈最终窗口 revision：`b93594fa738fc916f936946fddc4fbfcdc9cdcd6`，
  `source_branch=codex/localize-provider-errors`、`source_dirty=false`，
  `acceptance_state=candidate`、`promotion_pr=none`，liveness ok，窗口前后身份一致。
  审计早期其他任务曾更新 candidate；没有本任务的 sync/promote/deploy。
- site 为 `site_magick-ai-local`，URL/account 与旧 M4 数据库证据一致；没有使用
  本机 8010 开发库的旧 `cloud-db.json` 作为 M4 依据。
- Cloud 使用 repeatable-read/read-only 事务并 rollback；WordPress 用 SHORTINIT
  及 SQL 只读一致性事务，不加载插件/cron。原始 `status()` 直接调用部署中的只读方法，
  未通过 runtime execute 创建 run；Addon 对其做纯归一化回放，不写缓存。
- 此证据是 candidate 的只读事实，不是 clean-master M4 acceptance、签名 HTTP
  端到端、生产验收或新的检索质量结论。

本地忽略证据目录：`build/cloud-owner-readonly-20261016T122409Z/`。
`reconciliation.json` SHA256 为
`f6a9659bbf684a62397a884a6eea10fdb779f1288a2ef2daf19144a09c4bef3f`；
`bracket-summary.json` SHA256 为
`d9f1658f0ca989c0ac601987ae0acad818d8fa3e83f6d1f19d1226128f90331d`。
本次校验了 `SHA256SUMS` 全部条目。原始文件保留本地，不提交 keys 清单、正文或密钥；
换机若文件不可得须标记证据缺口，不能仅凭摘要重建完整审计。

### 最新覆盖与状态

| 对象 | 20:29 采样结果 | 含义与限制 |
| --- | --- | --- |
| 公开 post/page 分母 | 2,000 条已发布，排除 1 条密码保护，合计 1,999 | 当时分母；不沿用旧 1,997 |
| 当前公开覆盖 | **1,997/1,999**，缺 2 条 | 当前不能称 100%；缺口详见下节 |
| Cloud 历史额外记录 | 64 条精确 keys 与旧集合一致，50 post、14 page，78 chunks | 全部 ID 本地任意状态下均不存在；与两条缺口分开 |
| Cloud 总量 | 2,061 post/page + 69 media = 2,130 documents，2,362 chunks | 不能用数量差抵消缺口 |
| Cloud 原始状态 | ready、quota ok，active_run 为空 | coverage basis 无全站分母，ready 不能代表当前公开清单全覆盖 |
| 检索验收投影 | 原始 `status()` 没有 `retrieval_acceptance` | Addon 纯回放生成 pending/automatic=false、空验证时间/revision；不是自动验收排队 |
| embedding/maintenance | chunks 与 DB-managed preview 同为 Ollama/qwen3、1024 维；maintenance not_required/none，target 为 SiliconFlow/bge-m3 | 不因固定目标投影不同而重建；未重新调用模型或检索 |
| 开发计划额度 | 共享 `free_v1` 两个上限仍为 99,999，3 订阅/快照/账号受影响；本站无覆盖 | 计划级开发设置，不是生产或账号专属政策 |

### 新增公开覆盖缺口（与三项独立后续分开）

`post:282639`、`page:282641` 均在 `2026-10-16T12:17:16Z` 发布/修改，最终采样
仍未在 Cloud 入库。这两条不在采样时的 4-ID delivery buffer 中，不能称“都已排队”。
buffer attempts=0、最近本地投递成功、dropped=0、无错误码、无活动全量维护 cursor；
这些本地事实不能覆盖两条入库缺口。

当时已有 flush 时间为 `12:29:28Z`，reconcile 为 `12:47:21Z`（北京时间
20:47:21）。本次没有等待或触发这些动作，未证明它们后来执行或修复缺口。
下一步在既有 reconciliation 自然运行后只读复核精确两条及当时公开清单；
若仍缺失，再由站点/Addon 维护任务诊断既有投递链路。不手动 flush、清理或重建，
不将缺口归因于历史 64 条，也不关闭 F-01/F-02/F-03。

## 3. 历史已完成面与证据边界

下表保留原完成证据；当前覆盖与状态以上方 20:29 的带时间戳采样为准。

| 对象 | 可确认的状态 | 来源、时间和限制 |
| --- | --- | --- |
| Addon 0.4.1 运行时代码 | 刷新状态、失败反馈和有界 Site Knowledge 恢复已合并 | [候选记录](041-final-release-record-2026-10-14.md)；不等于所有后续已完成 |
| WordPress.org 发布 | 已提交 SVN `r3749721`；API、官方下载解压内容、英/中文目录页与 changelog 已核对 | [10 月 16 日发布日志](wordpress-org-release-and-translation-log.md#2026-10-16-041-wordpressorg-publication-and-post-publish-verification)；本次未重新下载或上传 |
| 官方 zh_CN 语言包 | 0.4.1 包 HTTP 200，已安装并核对文件哈希、locale、版本与固定中文字符串 | [语言包记录](wordpress-org-release-and-translation-log.md#2026-10-16-zh_cn-language-pack-propagation-and-local-installation)；此待办已关闭，不需重复安装 |
| 10 月 15 日公开 post/page 恢复 | 1,997/1,997，10/10 Cloud runs 成功 | [10 月 15 日恢复](site-knowledge-quota-recovery-closeout-2026-10-15.md)；仅代表当时分母，本次 1,997/1,999 不再全覆盖 |
| 本地投递状态 | 当时 idle、buffer 0、dropped 0、最近投递成功 | 10 月 15 日恢复及 [10 月 16 日 preflight](cloud-preflight-and-release-readiness-2026-10-16.md)；未实时重新读取 |
| Cloud 索引 | preflight 为 fresh/ready，2,130 documents、2,362 chunks，quota ok | M4 观察窗口 `2026-10-16T04:51:00–05:01:19Z`，当时 accepted source 为上表 Cloud SHA；本次未验证部署状态 |
| 人工检索 | 近期/中段/较旧标题检查 3/3，预期文档 rank 1 | `2026-10-15T04:11:14Z`；不构成自动验收或全语义质量评估 |
| 自动检索验收 | 未建立已完成证据 | 20:29 已确认原始字段缺失、pending 来自 Addon fallback；正式语义仍待 Cloud owner 决定 |

投递完成、索引完成、人工检索通过、自动验收完成是四个不同结论。
本记录只保留各自已有证据，不相互推导。

## 4. Cloud owner 尚待决定

| 问题 | 已知现象 | 下一步与正式落点 |
| --- | --- | --- |
| 历史记录 | 64 个精确 source keys 未变；本地缺失，未执行清理 | 独立 F-01；建议先确认权威源/恢复史及恢复方案，再准备有界清理，不批准执行 |
| 状态投影 | 已确认原始字段缺失、pending 来自 fallback；无自动验收完成证据 | 建议明确“未提供验收证据”，不解释成排队；Cloud 正式定义后另评估消费方适配 |
| embedding | 实际 Ollama 空间与固定 SiliconFlow maintenance target 不同；当时 not_required/none | 决策包 B；不据此在 Addon 启动重建 |
| 配额范围 | 共享 `free_v1` 两个资源上限仍为 99,999；目标账号聚合 2,061 text/69 media，另两账号索引用量为 0 | 建议明确开发例外，评估测试账号额度，再决定共享计划调整；不直接改回 100，不据此制定生产政策 |

本次补入已有只读证据，没有改变 Cloud 数据/配额或作 owner 决策；历史数据与开发环境的差异
不能自动生成 Addon 待开发需求。

## 5. 三项独立后续登记

### F-01：64 条历史记录的处置

- 状态：待 Cloud owner 决定；没有清理。
- 责任：Cloud owner 与站点管理员核对身份/来源，运营另行执行批准方案。
- 下一步：20:29 已刷新并确认原 64 条集合不变；按决策包 A 补 owner 权威源/恢复史确认、
  正式处置决定和恢复方案。未来执行前再次刷新清单，不能把本次建议写成批准。
- 完成条件：正式决定有 owner、日期和链接；选择清理时还需独立执行、恢复准备及
  结果核对。决策和执行分别记录，不能用总数变化替代精确 keys 证据。

### F-02：历史 OCR 真实证据及候选门禁状态

- 状态：历史完整真实审查证据有缺口；本次没有调用模型或重试。
- 已有事实：候选实现做过共享计数、冷却、锁、HEAD 绑定、结果复用等离线验证；
  真实审查曾因预算、超时、EOF、取消、prompt 上限或限流无法完整交付。
- 本次源码核对：Addon 当前 `scripts/publish-pr.sh` 不调用 OCR 门禁，
  `composer.json` 未提供本地 OCR 命令。GitHub API 核对 workflow 状态为
  `disabled_manually`；仓库 YAML 仍声明自动 PR 事件。因此旧交接中“已实现”
  只可描述候选，“已全面落地到 Addon master”不成立；不要启用 workflow 验证它。
- 权威关系：Addon 发布前审查仍遵守 `AGENTS.md`；Toolbox 的共享标准/ADR 与
  历史“本地仅显式发布触发”候选方向有差别，需要 owner 在后续采用前核对。
  Cloud 使用自己的 [OCR usage policy](https://github.com/npcink/npcink-ai-cloud/blob/master/docs/ocr-review-usage-policy-v1.md)，
  不能将 Addon 的停用状态推广成 Cloud 也已停用。
- 下一步：找到原候选/range/HEAD 和可恢复材料，先做不调用模型的 scope preview；
  在确有候选发布需求、预算与配置可用时，按所属仓库当时有效规则执行有界审查。
  失败记录不得覆盖，不能为补历史台账例行扫全仓或反复探测额度。
- 完成条件：精确范围/HEAD、实际覆盖、完整交付状态、findings 处置和证据位置
  均可核对。后来审查新 HEAD 只证明新范围，不能追认旧失败为成功。
  部分输出、退出码 0、action 版本更新或确定性测试通过都不能替代完整真实证据；
  有规则允许的例外时也应单列，不能称“审查通过”。

### F-03：AI Cloud 5 个无关 Dependabot PR

10 月 16 日只读审计再查确认以下五项 open，base 均为 `master`；本次仅补录该证据。
这里不评定它们的
当前 CI、漏洞严重度或合并资格；执行维护时重新获取 head、diff、版本意图与 checks。

| PR | 更新内容 | 本次登记状态 |
| --- | --- | --- |
| [#1101](https://github.com/npcink/npcink-ai-cloud/pull/1101) | alibaba/open-code-review 1.12.11 → 1.12.13 | open，独立维护 |
| [#1102](https://github.com/npcink/npcink-ai-cloud/pull/1102) | autoprefixer 10.4.20 → 10.6.1 | open，独立维护 |
| [#1103](https://github.com/npcink/npcink-ai-cloud/pull/1103) | tailwind-merge 3.5.0 → 3.7.0 | open，独立维护 |
| [#1104](https://github.com/npcink/npcink-ai-cloud/pull/1104) | fastapi 0.141.1 → 0.143.0 | open，独立维护 |
| [#1105](https://github.com/npcink/npcink-ai-cloud/pull/1105) | ruff 0.16.8 → 0.16.10 | open，独立维护 |

- 责任/入口：Cloud 维护者，按
  [PR 与依赖更新政策](https://github.com/npcink/npcink-ai-cloud/blob/master/docs/pr-and-dependency-update-policy.md)逐项处理。
- 下一步：先核对安全与兼容影响，再排序；遵守可信 bot 合约及受保护检查，
  不为此修改 Addon 或启用其 OCR workflow。
- 完成条件：每个 PR 有合并/关闭/替代结论与依据；涉及 runtime/lockfile 时有政策要求的
  M4 证据，生产仍走独立流程。仅更新 OCR action 版本不关闭 F-02。

三项各自推进，不把“Addon 0.4.1 已发布”“本包已交接”或“某个依赖已合并”
当作其他后续完成的证据，也不将它们捆成一个阻塞所有维护的总任务。

## 6. 下一次执行入口

| 顺序 | 工作 | 输出及停止点 |
| --- | --- | --- |
| 1 | Cloud owner 按决策包逐项回填 | 在 Cloud 仓库落正式决定；事实不足则记缺口和负责人，停止对应写操作 |
| 1（独立复核） | 既有 reconciliation 自然运行后核对 `post:282639`、`page:282641` | 刷新当时分母和精确缺口；本次未等待或触发任务，不能宣称已恢复 |
| 2 | 如需历史清理或 Cloud 契约实现，另开 Cloud 任务 | 绑定环境/范围/验证/恢复；本草案不代替操作授权 |
| 3 | F-02、F-03 分别在所属 owner 的任务中处理 | 使用各仓规则，不混入 Addon 功能或彼此自动结案 |
| 4 | 有真实跨仓实现里程碑时收口 | 核对各仓分支/SHA/工作区后，由 Toolbox 运行完整矩阵；需要 M4 时显式选运行时通道 |

只读复核可从以下命令开始，不调用付费模型、不修改 Cloud：

```bash
git -C /Users/muze/gitee/npcink-cloud-addon status --short --branch
git -C /Users/muze/gitee/npcink-cloud-addon rev-parse HEAD
git -C /Users/muze/gitee/npcink-ai-cloud status --short --branch
git -C /Users/muze/gitee/npcink-ai-cloud rev-parse HEAD
composer --working-dir=/Users/muze/gitee/npcink-workflow-toolbox quality:matrix
gh pr list --repo npcink/npcink-ai-cloud --state open --author app/dependabot --limit 30 --json number,title,headRefName,baseRefName,updatedAt,url
gh api repos/npcink/npcink-cloud-addon/actions/workflows/ocr-review.yml --jq '{path,state,html_url}'
```

完整矩阵另按 Toolbox 当前标准执行 `composer quality:matrix:run`；
它不部署或同步 M4。对未提交工作或其他任务分支只记录，不为凑干净窗口强制清理。
Addon 正常自动投递保持安静，真实失败才使用既有恢复入口。

## 7. 后续更新记录模板

每次仅更新实际变化的条目，保留前次证据指针。无法重新验证时写“未复核”。

```text
更新时间/时区、记录人：
对象或后续 ID：
环境、source SHA、index revision（如适用）：
原状态 → 新状态：
动作/命令、证据产生时间、结果：
证据链接（脱敏）、完整/部分/不可得：
决定人及正式决定链接（如适用）：
剩余缺口、下一步、责任人：
```

本次仅形成文档草案，不改 Addon 代码。验证与适用性见决策包第 8 节；
本记录不宣称新增 M4、Provider、浏览器、完整跨仓或历史 OCR 验收。
