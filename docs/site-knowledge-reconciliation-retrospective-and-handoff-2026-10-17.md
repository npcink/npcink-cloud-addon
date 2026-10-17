# Site Knowledge reconciliation 复盘与会话交接 — 2026-10-17

状态：源码修复已合并；真实恢复验收仍开放。本记录保存诊断、取舍和交接边界，
不批准站点更新、游标回退、内容补送或 Cloud 索引操作。
时间采用 Asia/Shanghai，并保留原始 UTC。

## 1. 已交付与未完成的分界

| 对象 | 已有证据 | 本记录的结论 |
| --- | --- | --- |
| Cloud owner 决策包 | [PR #281](https://github.com/npcink/npcink-cloud-addon/pull/281)，merge `243c81f` | 文档已交付；owner 尚未作正式决定 |
| 只读证据补录 | [PR #282](https://github.com/npcink/npcink-cloud-addon/pull/282)，merge `0ae75fe` | 当时覆盖 1,997/1,999，保留时间与环境 |
| 游标与容量修复 | [PR #284](https://github.com/npcink/npcink-cloud-addon/pull/284)，merge `ba6a621c019ee894df5f1b96a6c16985cacf2c71` | 已进入 master；最终 head `f18c93f` 与 squash 合并结果 tree 一致 |
| 修复验证 | 本地确定性门禁、一次性 Playground、52 文件打包校验；最终 13 项 checks 全部成功 | 源码验证完成，不等于真实站点或 Cloud 恢复 |
| 本地实际站点代码 | 本次文档收口开始时主目录为 `b80b966151f26f114271a5534082c336ab2fea50`，工作区干净 | 未应用 reconciliation 修复；本次不快进该目录 |
| 两条缺口 | 最后一次入库核对为 2026-10-16 21:12:05 | 仍待另行验收；本次未刷新 WordPress/Cloud |
| 自然运行观察 | 已观察到执行标记推进，随后完成一次 Cloud 核对；heartbeat 已 PAUSED | 原观察约定已结束，不自动恢复轮询 |

本地站点插件链接指向 `/Users/muze/gitee/npcink-cloud-addon`。
快进这个目录会实际改变站点所加载代码，因此 Git 清理不包含主目录更新。
工作区干净、PR 已合并、运行时已更新、覆盖已恢复是四个不同状态。

## 2. 从“到期未运行”到“运行后仍缺失”

10 月 16 日原 reconciliation 计划为 `12:47:21Z`，等待基线
`last_reconciled_at=12:09:50Z`。只读排查发现未配置
`DISABLE_WP_CRON`，无有效 doing_cron 锁，hourly 事件仍在，
verified 与 delivery consent 开启，另两个到期事件同时积压。

WordPress 7.1.3 的默认 cron 触发链需要完整请求进入 init，再于 shutdown
尝试回环启动。到达计划时间不会独立产生进程；SHORTINIT 采集器不会提供这种触发。
已检查范围内没有站点外部定时驱动，访问日志关闭且未见当日错误日志。
因此当时只能推测缺少自然触发机会，不能排除静默回环失败或启动阶段中断。
本聊天未通过前台请求、wp cron 或 flush 来制造执行。

后续执行标记实际推进至 `13:06:24Z`，total_sent 从 8,377 增至 8,381，
原 4 条其他 ID 的 buffer 清空，下次 reconciliation 排到 `13:47:21Z`；
持久扫描 cursor 仍空。

随后的稳定窗口为 WordPress 前采样 `13:12:04Z`、
Cloud 事务快照 `13:12:05.648095Z`、WordPress 后采样 `13:12:05Z`。
公开清单的类型、ID、密码保护标记和修改时间前后一致，Cloud 使用
REPEATABLE READ / READ ONLY 并 rollback。M4 `m4-preview` 报告运行 revision
`2fd7282fe28bd4e73697587ebe961afd2cee2c3d`；这不是本次新部署或 accepted-master 证明。

| 类型 | 当时公开分母 | 当时覆盖 | 全部当时缺口 |
| --- | ---: | ---: | --- |
| post | 1,966 | 1,965 | `post:282639`：无 document，0 chunks |
| page | 33 | 32 | `page:282641`：无 document，0 chunks |
| 合计 | 1,999 | 1,997 | 仅以上两条 |

2,000 条已发布 post/page 中排除了 1 条密码保护内容。
额外历史 64 个 Cloud keys 与旧集合逐项一致，不能用它们抵消当前缺口。
以上都是历史采样，本次文档发布没有将其冒充成 10 月 17 日实时覆盖。

## 3. 根因与最小取舍

旧逻辑在持久 cursor 缺失时，每次借用最新 `last_delivered_at`。
局部成功、空 buffer success 或 maintenance 等待都可能更新这个时钟；
它不证明所有更早修改的内容已枚举或已覆盖。

两条目标的修改时间均为 `12:17:16Z`，诊断时的回退水位为
`12:26:28Z`。实际只读 SQL 按时间和 ID 水位选择，返回 0 条。
空结果分支只更新 last_reconciled_at，不写持久 cursor，因而可以出现
“任务运行了、游标仍缺失、历史缺口继续被跳过”。
这解释了补扫漏选；原始 save/transition 投递链为何最初没有交付两条，仍无充分证据。

修复复用现有 50 候选扫描、500-ID 缓冲和普通 25-ID 投递：

- 缺失 cursor 从最早候选开始，SQL 第一页不使用空 DATETIME 下界。
- 已有有效 cursor 保持权威，不按当前时间重置，不做迁移或回退。
- 按可用缓冲余量接纳连续候选，在首个无法接纳的 ID 前停住。
- 入队前重新检查容量，确认持久保留后才推进；已缓冲 ID 不占新容量。
- 不新增队列、调度器、锁或 UI；Cloud 继续拥有索引执行、生命周期和 freshness。

没有选择固定回看窗口或 last_reconciled_at 回退，因为它们同样没有完整覆盖证明。
没有选择 rebuild/全量清理来掩盖两条缺口，也没有扩大为通用历史索引修复系统。
按当时静态清单，两条目标都在从头扫描的第 40 页；实际时长受自然触发、
容量和投递进度影响，不能承诺“40 小时恢复”。补扫也可能重发已覆盖内容并消耗配额。

## 4. 可复用的开发与验证方法

1. 先保留事实，再做改动。记录环境、revision、时间窗口、原始字段和精确 source keys；
   保留旧采样，不用计划时间或总量推导执行与覆盖。
2. 用可失败的回归锁定错误假设。原测试明确排除最近投递之前的内容，
   新断言在原 bridge 上失败，在修复后通过，不能只给旧预期换个名字。
3. 依次验证选择、接纳、持久化、投递和索引。测试覆盖空初始化、时钟独立、
   有效游标同秒边界、满载/一格余量、已有 ID、写入失败和腾出容量后继续。
4. 真实 SQL 与测试 stub 分开。一次性 Playground 验证无水位初始查询和同秒 50+1，
   不把 PHP stub 的通过当作 SQL 或真实 Cloud 验收。
5. 隔离开发与站点加载目录。在独立 worktree 实施；其他 feedback 变更合入后更新基线，
   重新验证集成，避免把共享目录切换或清理变成隐式部署。
6. 先通过完整发布门禁再收口。初次 Release static gates 因 PHP 行移动后的 POT
   引用过期失败，刷新 POT/PO 后通过；没有改变字符串内容，641 个编译 zh_CN 映射一致。
   即使没有新增文案，仍应执行 i18n freshness 检查。
7. 审查失败必须可见。本地 OCR 选中 3 个 PHP 文件，8 分钟超时，消耗 120,971 tokens，
   0/3 完整交付；零 finding 不等于通过。CI OCR 当时 disabled_manually，
   没有为补台账启用它或追加付费重试，也不因此关闭历史 F-02。

长期规则分别保留在 [投递契约](site-knowledge-full-index-delivery.md) 和
[验证证据标准](verification-evidence-standard.md)；本复盘不另立 Cloud 权威契约。

## 5. 证据保存与仓库清理交接

作者机上的忽略目录保留：

| 路径（仓库根目录下） | 内容 |
| --- | --- |
| `build/cloud-reconciliation-readonly-20261016T124223Z/` | 前后 WordPress 清单、Cloud 只读查询、精确集合对账和自然执行后结果 |
| `build/cron-readonly-diagnostic-20261016T125859Z/` | cron 配置/日志边界、执行停滞诊断 |
| `build/reconciliation-cursor-review-20261016T130610Z/` | 游标行不存在、实际 SQL 零候选、离线分页和最小方案 |
| `build/reconciliation-fix-closeout-20261017T013907Z/` | 修复测试、初次 CI 失败、OCR 未完成记录、最终 checks 和文件 hashes |
| `build/session-handoff-20261017T023240Z/` | 本次主目录基线、正式文档可读副本、合并核对、Git bundle、最终 Git 清理收据 |

这些原始材料不入 Git，不提交凭据、正文或完整历史 keys；换机不可得时应标为证据缺口。
正式结论与源文件已入库，原始证据需要另行受控转交，不能只依赖临时 worktree。

本次授权清理范围为本会话的三个历史 topic 分支、本文档 topic 分支，以及唯一辅助
worktree。其他任务的 `codex/recommendation-feedback-metadata` 不在范围内。
发布前保存 PR #281/#282/#284 的 head、squash commit、变更路径及内容核对；
本文档 PR 合并并确认完整 checks 后，再保存其同类证据。
删除前保存可校验 Git bundle 和需要的忽略文件，远端删除绑定已核对的 head，
发现新提交、未合并内容或未授权 worktree 时停止对应清理。

本文件提交时清理尚未执行。最终是否完成，以本地
`build/session-handoff-20261017T023240Z/handoff.md` 和机器收据为准；
归档后主目录仍应为开始时的 b80b966，工作区干净、未被快进。
正式文档在本地 Git 对象与远端 master 中保存，另外提供可读副本，
不为了显示新文档而更新站点所加载目录。

## 6. 必须继续保留的工作

| 后续 | 状态与责任 | 下一步与完成标准 |
| --- | --- | --- |
| F-01：历史 64 条 Cloud 记录 | Cloud owner 待决定；未清理 | 补身份/恢复史、正式处置决定；任何执行与恢复材料另行授权 |
| F-02：历史真实 OCR 证据 | 原范围尚有缺口，本次修复审查也未交付 | 按所属仓库规则绑定精确范围、预算、覆盖和 findings 处置；不追认旧失败 |
| F-03：5 个 Cloud Dependabot PR | 依赖维护独立保留；本次未复核其最新状态 | Cloud 维护者按 bot 合约核对 diff/checks，逐项形成合并、关闭或替代结论 |
| 两条缺口恢复验收 | 源码修复已合并，真实恢复未验收 | 先另行授权站点代码更新，记录实际 revision/游标；自然运行后在稳定公开清单窗口核对 documents/chunks、当前分母和全部缺口 |

有效游标若已越过两条目标，初始化修复不会自动补回；应另案核对并授权精确补送，
不能回退游标。若分母仍为 1,999 且无其他新增缺口，验收目标为精确 1,999/1,999；
分母变化必须重算。ready、执行标记、投递计数和 PR 合并均不代替此验收。

本次仅完成文档与 Git 资源收口，不访问站点、不运行 cron/flush/sync/promote、
不启用 OCR 工作流、不改配额、不清理 Cloud 数据、不重建或部署。
