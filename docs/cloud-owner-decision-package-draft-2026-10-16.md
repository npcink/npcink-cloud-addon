# Cloud owner 决策包（草案）— 2026-10-16

状态：待 Cloud owner 评审；未作保留、清理、重建或生产配额决定。

本文是 Addon 消费方交接材料，不是 Cloud 新契约、清理授权或操作脚本。
Cloud owner 应在 `npcink-ai-cloud` 保存正式决定、受支持的执行方案和结果，
本仓库只引用其链接。当前证据总表见
[当前状态真相记录草案](current-state-truth-draft-2026-10-16.md)。

## 1. 本次交付与责任

本次仅整理文档；没有调用 Cloud 写接口、Provider、索引操作或 WordPress 写入，
没有调整配额、启用 OCR、合并依赖或发布插件。

| 角色 | 本包中的责任 | 待补信息 |
| --- | --- | --- |
| Cloud owner | 确认站点身份、历史数据策略、状态投影和配额适用范围；在 Cloud 仓库落正式结论 | 负责人、决定链接、预计评审时间 |
| Cloud 运营执行者 | 按正式方案准备 dry-run、恢复材料、执行窗口和结果证据 | 负责人、受支持入口、环境 |
| WordPress 站点管理员 | 确认当前站点和公开内容范围，提供有时间戳的只读清单 | 负责人、清单时间、站点身份依据 |
| Addon 维护者 | 说明现有投递/读取行为，核对 Cloud 结论是否影响消费方契约 | 当前仅交接；不接管 Cloud 生命周期 |

角色尚未落实时，记录为“待分配”，不能据此推定已批准任何操作。

## 2. 已知事实与适用环境

最新只读采样窗口为 `2026-10-16T12:29:20Z–12:29:28Z`（北京时间
20:29:20–20:29:28）。本次文档更新核对了保留证据的校验值，没有再次访问或修改
Cloud/WordPress。将来执行任何操作前仍须刷新目标环境、revision、站点归属与精确清单。

### 10 月 16 日只读刷新

M4 开发栈采样 revision 为 `b93594fa738fc916f936946fddc4fbfcdc9cdcd6`，
`source_branch=codex/localize-provider-errors`、`source_dirty=false`、
`acceptance_state=candidate`、`promotion_pr=none`。采样前后运行时身份及 WordPress
公开清单/修改时间一致。审计期间其他任务曾更新 candidate，因此此证据不升级为
clean-master M4 acceptance 或生产验收。

| 事实 | 证据与限制 |
| --- | --- |
| 当前公开覆盖为 **1,997/1,999** | 2,000 条已发布 post/page 中 1 条密码保护内容排除；另有 2 条公开内容未在 Cloud 存在，不能继续称当前全覆盖 |
| 两条新增覆盖缺口 | `post:282639`、`page:282641`，均在 `12:17:16Z` 发布/修改；与历史 64 条分开，见状态记录的“新增公开覆盖缺口” |
| Cloud 有 2,061 条 post/page，额外历史集合仍恰为旧 64 个 source keys | 50 posts、14 pages，关联 78 chunks；全部 ID 在当前 WordPress 任意状态下均不存在；不是只比较数量 |
| Cloud 总计 2,130 documents、2,362 chunks | 总量另含 69 media；不能将 2,130 与 post/page 清单直接相减判缺失 |
| 实际 chunks 使用 `ollama-m4-embedding:qwen3-embedding:0.6b`，1024 维 | DB-managed local-preview 配置与实际空间一致；纯配置验证器回放通过，没有调用 embedding Provider 或重新检索 |
| maintenance 原始输出为 `not_required / none`，target 为 `siliconflow:BAAI/bge-m3` | 无活动批次或 request_id；其 automatic=true 是维护字段，不能当作检索验收证据 |
| Cloud 原始 `status()` 顶层没有 `retrieval_acceptance` | 对捕获结果做 Addon 现有纯归一化回放才得到 pending、automatic=false、空验证时间/revision；不是 Cloud 自动验收排队 |
| Cloud 原始状态为 ready、quota ok | coverage basis 为 `cloud_index_counts_without_whole_site_denominator`；ready 不能证明 WordPress 公开清单全覆盖 |
| M4 共享 `free_v1` 的两个上限仍为 99,999 | 3 个活跃订阅/快照、3 个账号受用；目标账号无资源覆盖；不是生产政策 |

历史 64 个 keys 按 `(source_type, source_id)` 排序，以每行 `type:id` 加换行序列化，
SHA256 为 `c66b6c6b48fdbcbdf66fab73c9d8dc06be98e7b4afb1883beeda527fff327106`。
其中 62 条最后索引于 8 月 22 日，2 条于 8 月 25 日；含历史内容，不能全部归类为 fixture。

### 历史证据与保存位置

[10 月 15 日恢复记录](site-knowledge-quota-recovery-closeout-2026-10-15.md)的
1,997/1,997、10/10 批及人工标题 3/3 仍是当时的通过证据，不代表本次 1,999 分母的
全覆盖或自动验收。本次没有重跑检索。旧
[preflight](cloud-preflight-and-release-readiness-2026-10-16.md)的 pending 观察应按
消费方投影理解；本次分层取证已确认原始 Cloud 字段缺失。

刷新证据位于忽略目录 `build/cloud-owner-readonly-20261016T122409Z/`，包括
`cloud-readonly.json`、`wordpress-before.json`、`wordpress-readonly.json`、
`runtime-bracket-before.json`、`runtime-bracket-after.json`、`bracket-summary.json`、
`reconciliation.json`、`historical-source-keys.txt`、`historical-64-records.json` 和
`SHA256SUMS`。对账文件 SHA256 为
`f6a9659bbf684a62397a884a6eea10fdb779f1288a2ef2daf19144a09c4bef3f`。
Cloud 使用 repeatable-read/read-only 事务并 rollback；WordPress 用 SHORTINIT 和
SQL 只读一致性事务，不加载插件/cron。原始状态直接调用部署只读方法，没有通过
runtime execute 创建 run，没有签名 HTTP 端到端或新的 Provider 证据。

已有本地证据位于忽略目录 `build/cloud-preflight-2026-10-16/` 和
`build/quota-recovery-2026-10-15/`。这些路径是查找线索，不保证换机可得；
文件不可得时记录证据缺口，不能伪造完整审计。完整 source keys 和恢复材料应
由 Cloud owner 受控保存，本仓库只保留脱敏摘要、数量和校验值，不提交密钥或正文。

## 3. 待决问题 A：64 条历史记录（独立后续 F-01）

决策对象是这次审计识别的精确记录集合，不是“Cloud 比本地多出的任意 64 条”。
本地缺失不足以证明 Cloud 记录可删，还需核对站点是否重置、迁移或恢复过数据库。

**建议方向（未批准）：** owner 确认当前 WordPress 为有效权威源、排除恢复/迁移歧义后，
准备精确有界清理方案，不建议无期限保留在活跃检索索引。确认与恢复方案完成前临时
保留；本次证据和文档合并都不批准执行，也不把两条当前覆盖缺口并入此集合。

### 评审前准备

- [ ] Cloud owner 核对环境、account/site 归属、站点 URL、revision 和审计时间。
- [ ] 站点管理员确认当前 WordPress 数据库是否为该 Cloud site 的有效内容源。
- [ ] 重新导出只读公开 post/page 清单，并与 Cloud 同类型记录对账；media 单列。
- [ ] Cloud owner 保存精确 source keys、类型、关联 chunks/索引记录、最后索引时间，
      标明旧审计集合与新集合差异，生成稳定排序清单的校验值。
- [ ] 将历史内容、测试数据、身份无法确认项分别分类；不能只核对数量。

### 待选择的处置

| 选项 | owner 需要说明 | 后续动作 |
| --- | --- | --- |
| 保留 | 合法来源、检索影响、保留理由、复核时间或触发条件 | 记录保留结论；若需改变检索资格，由 Cloud 单独设计 |
| 有界清理 | 精确范围、受支持操作、依赖数据、备份/恢复、批准人和窗口 | 另开 Cloud 执行任务，先 dry-run，再按批准清单执行 |
| 延后决定 | 缺少的事实、补证责任人、下次评审触发条件 | 维持未决；不自动推定保留策略或启动删除 |

### 仅在选择清理后使用的执行清单

此处只列交接验收要求，具体命令和存储清理由 Cloud owner 在其仓库制定。

1. 准备经审查的 Cloud 操作方案：精确 site/source-key 条件、最大影响范围、
   并发内容变更处理、事务或分批失败处理、停止条件及可恢复材料。
2. dry-run 输出目标清单和关联影响，与批准清单逐项比较并绑定校验值。
   站点身份、类型、集合或数量与批准内容不符时，停止并返回 owner 复审。
3. 执行者记录实际批准人、时间、环境、Cloud revision、方案版本和恢复位置。
   本草案、绿色矩阵或 Addon 本地 consent 都不代替该次操作批准。
4. 在批准窗口通过 Cloud 支持的入口执行；不得用 Addon 全索引 delete/rebuild
   代替精确清理，也不得以更新后的数量差自动扩大范围。
5. 记录实际受影响 keys、关联索引变化、失败项和执行结果；异常按既定停止/
   恢复方案处理。未恢复或未核对的部分必须继续保持未完成状态。
6. 重新核对当前有效公开清单覆盖、未目标记录和 media 是否保持预期，复核同一组
   有界检索与实际 embedding 兼容性；记录当时分母，不能要求总量机械减去 64。

F-01 的“策略已决定”和“清理已执行并验证”分开记录。保留决定不需要清理执行；
选择清理后，只有执行和恢复证据核对完成，才可关闭操作子项。

## 4. 待决问题 B：状态投影语义

owner 应针对现有 [Cloud Site Knowledge 契约](https://github.com/npcink/npcink-ai-cloud/blob/master/docs/site-knowledge-runtime-contract-v1.md)
确认下列问题，并在 Cloud 仓库记录结论。下表是问题清单，不定义新枚举或字段。

| 决策点 | owner 需要交付的答案 | 可核对的证据 |
| --- | --- | --- |
| `retrieval_acceptance` 字段缺失 | 是版本兼容、尚未实现，还是其他含义；消费方应如何解释 | 原始 Cloud 输出与 Addon 归一化输出成对样例，标明采样层 |
| `pending` 和 `automatic=false` | 是否有自动验收机制、谁触发、何时运行；无机制时应如何表达 | 实现/契约链接；不能用文字推定后台任务存在 |
| 已完成验收的适用范围 | 验收类型、样本、验证时间、index revision，以及索引变化后何时失效 | 与确切索引版本绑定的证据；人工 3/3 另列 |
| maintenance target 与实际 embedding | target 表示配置目标还是当前有效空间；preview 与 production 是否有差别 | 实际存储、查询使用空间和投影样例；同为 1024 维不能证明兼容 |
| 生命周期触发 | 在 `not_required / none` 时如何解释差异；哪些条件才进入维护 | Cloud 实现及相关测试/运行时证据；不能在 Addon 补调度逻辑 |

**建议方向（待 Cloud 正式定义）：** 字段缺失表达“未提供验收证据”，不要解释成
自动验收排队；pending/passed 应有相应的实际状态及版本证据。区分当前有效 embedding
空间和固定维护目标，明确 preview 例外；不要为消除 pending 新建调度器或触发重建。
这些是 owner 决策输入，不在本仓库新增枚举、验收机制或运行时政策。

决策完成后的消费方处理顺序：

1. Cloud owner 发布正式结论和兼容性说明；若无需改动，明确“仅解释现有契约”。
2. 若需实现变更，由 Cloud 单独交付测试与版本绑定的运行时证据。
3. Addon 维护者仅评估已有读取/回退/文案是否需要适配。需要改动时另开小任务，
   遵守现有后台规范及验证标准；本草案不授权修改 Addon 代码。

## 5. 待决问题 C：开发配额适用范围

本次仍确认 published `free_v1` 于 `2026-10-15T03:41:52.371251Z` 更新为两个
99,999 资源上限。目标账号快照无覆盖，继承计划；账号聚合用量为 2,061 text
documents、69 media，另外两个受影响订阅账号当前索引用量为 0；账号聚合补充采样为
同一 candidate revision 的 `12:28:53.527474Z`，保存在 `provider-quota-supplement.json`。
每站实现上限
另为 10,000 documents / 200,000 chunks，与账号额度不是同一层。

**建议方向（未调整）：** 将 99,999 明确为待复核的 M4 开发例外，后续优先评估有预算的
测试账号额度，再决定共享计划如何收回。先核对全部受影响账号，不直接恢复 Free=100：
即使以后清理 64 条，存量仍明显超过 100，可能重新阻塞投递。零用量也不代表其他账号
权益可被忽略；生产配额继续单独决定。

- [ ] Cloud owner/运营确认 M4 的 `free_v1=99,999` 是临时开发设置还是有意保留。
- [ ] 明确计划级与账号级的目标使用范围、受影响订阅、复核条件及后续负责人。
- [ ] 如需生产政策，另给生产决定链接、环境证据和发布流程；不复制开发值。

本项交付是配额适用范围说明。调价、账务或实际配额变更由 Cloud 另行处理，
不需要 Addon 新增存储字段或控制面。

## 6. owner 回填模板

每个问题单独回填，未决定项保留“待决定”。正式决定原文归 Cloud 仓库所有。

```text
问题：A 历史记录 / B 状态投影 / C 配额适用范围
状态：待决定 / 已决定（执行状态另列）
负责人及决定人：待分配
决定时间、时区：待填写
目标环境、site、Cloud revision：待复核
证据位置、采样时间、清单校验值：待填写
结论、理由、适用范围：待决定
未解决事项、下次复核触发条件：待填写
执行是否需要、执行任务链接：待决定
批准范围、窗口、停止条件、恢复材料：执行前填写
执行结果与验收证据：尚未执行
Addon 消费方影响：待评估；本任务不改代码
Cloud 正式决定/契约链接：待填写
```

## 7. 独立后续与本包完成条件

| 后续 ID | 保持独立的事项 | 下一步与关闭依据 |
| --- | --- | --- |
| F-01 | 64 条历史记录保留/清理 | 按第 3 节形成正式决定；若选清理，再独立执行与验证 |
| F-02 | 历史 OCR 完整真实审查缺口 | 见状态记录；核对原候选/range 和真实交付，不因本包完成而改为通过 |
| F-03 | AI Cloud 5 个 Dependabot PR | 见状态记录中的逐项链接；依 Cloud 依赖维护流程单独处理 |

两条新增公开覆盖缺口是当前投递证据的另列差异，不关闭、替代或抵消 F-01/F-02/F-03。
按现有 reconciliation 自然运行后的新证据判断；本次没有等待或触发 cron、手动 flush。

本草案可交接的条件是：事实带来源和时间，问题可逐项回填，执行前置条件与
停止/恢复要求明确，三项独立后续仍可追踪。它不以所有后续关闭作为交付前提，
也不代表 Cloud owner 已作决定。

后续跨仓实现完成后，按 Toolbox 的
[发布协调标准](https://github.com/npcink/npcink-workflow-toolbox/blob/master/docs/platform/pr-publishing-standard-v1.md)
收口；矩阵不负责批准清理、部署、配额或产品质量结论。

## 8. 文档验证与回滚

本次只修改 `docs/`；交付前执行 `composer run test:all` 和 `git diff --check`。
Playground、浏览器、Cloud/M4 runtime 和发布包门禁不适用：没有改动 bootstrap、
公共 API、凭据默认状态、兼容基线、后台行为或 release manifest 文件。
无需为本草案运行完整跨仓矩阵或付费模型审查；文档 PR 按仓库流程发布，
发布前仍需执行规定的 OCR 文件选择/审查并如实记录结果。
Markdown 未选中属于不适用，不补齐 F-02 的历史真实审查证据。
文档合并不代表 Cloud owner 已接受草案或批准任何 Cloud 操作。

回滚为撤回本次文档增量，不涉及数据恢复。未来若执行 Cloud 清理，必须使用
Cloud 方案单列的数据恢复路径，不能将文档回滚描述成数据回滚。
