# Site Knowledge 回执丢失：修复复盘与验收经验（2026-11-16）

状态：开发候选已实现并完成下列限定验证；未合并、未完成 exact-SHA CI、未晋级
M4 accepted、未部署生产、未获人工最终验收。本记录是当日证据，不自动代表未来状态。
活动要求见[验证证据规范](verification-evidence-standard.md#跨系统回执与中断恢复验收)；
行为真相仍在[Site Knowledge 派发契约](site-knowledge-full-index-delivery.md)。

## 1. 事件与已确认反例

历史事件能确认：Cloud 已执行成功，Addon 没有持久保存 `run_id`，共享 dispatch owner
留存，后续操作持续阻断。历史证据不能区分当时是 PHP 请求中断还是 CAS 保存冲突；
两者均已复现，不能把任一候选原因写成已确认的原始根因。

| 故障注入位置 | 修复前的反例 | 候选修复后的要求 |
| --- | --- | --- |
| Cloud 接受后，本地回执 CAS 冲突 | 忽略保存返回值，返回成功但 owner 留存 | fresh-state 合并重试并保留并发意图；耗尽时显式报保存失败，后续以原身份恢复 |
| Cloud 接受后、本地 finalizer 前退出 | 没有回执，后续派发被 owner 挡住 | 新请求通过持久原 key 只读查回 run，再核对精确 writer 终态 |
| 确定尚未发送，释放 owner 时 CAS 冲突 | Cloud 执行数为 0，仍然卡住 | 有限重试；后续取消 preparing owner，延迟的原 sender 必须无法通过发送前 CAS |

普通文章/图片同步、文章刷新、Toolbox/public facade、full/reconcile、排除、恢复和
图片撤回共用这条派发路径，因此都可能受影响。入口关系经过源码核对；没有逐入口
做真实端到端故障注入。生成、媒体 ACK、监控/反馈缓冲及 Portal 写入的相关检查
不能泛化成“其他地方全部安全”；它们不同的持久化和跨生命周期缺口仍须各自验收。

## 2. 为什么开发时没发现

1. 原测试覆盖了 HTTP 响应丢失后仍正常进入本地 finalizer，以及发送前的并发取消；
   没有在远端接受后注入本地保存失败，也没有跳过 finalizer。测试已接入 `test:all`，
   不是漏跑测试文件，而是故障模型缺了一段。
2. 数组 SQL double 没有真实 MySQL 的并发连接和 WordPress 请求内 option cache。
   CAS 失败后简单重读仍可能命中陈旧缓存，单纯增加重试次数不能证明修复。
3. 原契约保守阻断是为防止旧 writer 在删除之后复活数据；这项安全约束有依据。
   但验收只证明了“不会误放行”，没有同时要求“权威证据恢复后能够继续推进”。
4. 最初只恢复了当前故障并改善提示、工单预填，没有修复防复发路径。把用户暂时
   能继续操作当成根因已关闭，是交付判断错误；后续审计已纠正该状态。

## 3. 修复取舍与保留限制

- 保存回执和释放已知未发送 owner 最多尝试 4 次 CAS。每次重新读取权威状态，
  失败 CAS 失效 option cache；仅合并同 token/scope/key/fingerprint 的派发字段，
  保留并发 pending/source 意图。耗尽返回 `cloud_site_knowledge_receipt_not_saved`。
- 新 owner 持久化为 `preparing`，真正出网前必须以完整旧状态 CAS 转为 `sending`。
  取消 preparing token 后，旧进程即使恢复也无法通过该检查。这是取消安全的依据，
  不是“等待足够久就认为旧进程已死”。旧版没有 phase 的 owner 不可按未发送处理。
- 对 sending/旧版 owner，用签名 `GET /v1/runs/by-idempotency/{key}` 查当前站点
  既有 run，再复用完整 writer 身份和正常 executor 终态验证，才补存回执。恢复不
  POST replay、不创建 run、不付费执行，也不把 WordPress 意图归属转交给 Cloud。
- 派发前安排既有 Cron；后续 Cron 和派发都尝试恢复，不能依赖原 PHP 内存响应。
- **保留的未知窗口**：通过 sending 检查后、HTTP 尚未到 Cloud 就退出，此时查不到
  run 仍不能证明没有延迟 sender。记录缺失/过期、网络或权限错误、running、身份
  不符、auto-fail 或其他非可信终态也继续保护 owner。没有承诺消除所有阻断，不能
  通过超时清锁、404 或任意 `failed` 标签冒充安全恢复。

源码与可执行回归：

- [Change bridge](../includes/class-cloud-site-knowledge-change-bridge.php)：
  `dispatch_mutation`、`recover_dispatch_receipt`、`finish_dispatch_owner`、
  `compare_and_swap_delivery_state`。
- [回执故障回归](../tests/behavior-site-knowledge-receipt-recovery.php)：26 条 Receipt
  断言；由 [typed presence suite](../tests/behavior-site-knowledge-typed-presence.php)
  接入既有测试链。
- [Runtime client](../includes/class-cloud-runtime-client.php) 与
  [endpoint policy](../includes/class-cloud-runtime-endpoint-policy.php)：有界签名 GET。
- Cloud 对端：`app/api/routes/runs.py`、`app/domain/runtime/run_lifecycle.py`、
  `app/domain/runtime/service.py`；回归 `tests/api/test_runtime_receipt_lookup.py`。
  接口与归属见 Cloud 仓库 `docs/site-knowledge-runtime-contract-v1.md` 的
  “Dispatch receipt recovery (2026-11-16 candidate)”节。

## 4. 验证台账：各层证据不能互相替代

| 层级 / 范围 | 当日结果与限制 |
| --- | --- |
| Addon 确定性门禁 | `composer run test:all`、PHPStan、i18n freshness、diff check 通过；含保存冲突/耗尽、陈旧缓存、重启夹具、延迟 sender、身份/凭据隔离、running/unknown/absent、后续排除推进 |
| Playground | WP 7.0.4 / PHP 8.2 通过；兼容性证据，不代表真实并发 |
| Local MySQL / WordPress cache | 两个独立连接及真实陈旧 option cache；accepted / not_sent 均恢复并保留并发意图；临时表已删除 |
| 两个真实 PHP 进程 | preparing / sending 中断后由新进程恢复通过；数据库真实，Cloud HTTP 为 double，不能称为真实远端执行中 kill 验证 |
| Cloud 本地 / M4 测试 | API 与既有 runtime 生命周期合计 7 项分别通过；查询不增加 run、Provider 调用或 usage，包含权限、站点隔离和 key 边界 |
| 真实签名跨仓读取 | Local Addon → M4 找回历史 succeeded run，再按既有 run 接口验证；只读，未制造新执行或付费样本 |
| 当前本地测试站 | owner、pending operations、buffer 和临时表均为 0；只说明当时现场已恢复 |
| 中央跨仓 matrix | Addon passed；Cloud `needs_validation` / exit 78。脏候选无 exact-SHA GitHub CI，不可写成全绿 |
| 合并 / 晋级 / 生产 / 人工 | 均未完成；M4 为 dirty development candidate，不能作为生产证明 |

开发中曾修正测试自身未处理队列、scope 修改未提交、HTTP double 缺少 JSON Content-Type
的问题，随后定向重跑通过。发送前阶段检查由评审补出，修改后重跑完整 Addon gate、
PHPStan 和真实两进程验证；没有放松产品断言使测试通过。

原始日志与可重跑 Local probes 保存在当次独立证据目录，文件名为
`knowledge-receipt-audit.md`、`knowledge-receipt-fix-handoff.md`、
`knowledge-receipt-fix-receipt.json` 和 `npcink-receipt-*`。
M4 当日候选 source bundle SHA256：
`9ba54515841aa443c79cf92bce0f5ac365aafd366c1715472b2210aec8e9edb2`。
这些外部证据补充本记录；活动规则、失败模型和源码/回归入口必须留在 Git，不能只
依赖会话、机器绝对路径或可被清理的 worktree。

## 5. 后续开发必须沿用的经验

远端成功、本地回执持久化、后续操作推进、最终数据满足意图是四个独立事实。
有幂等 key 不等于可恢复：本地锁若挡住恢复入口，服务端 replay 自己不能解锁。
评审跨系统写入时，必须检查每个持久化返回值、请求中断后的入口、取消旧 sender 的
证明，以及真实证据回来后能否继续推进；具体故障矩阵见活动验证规范。

界面诊断应说明影响、已知原因或明确未知、下一步和验证方式。“检查连接”只核验
连接，不代表历史回执已补齐；工单自动预填帮助定位，也不能代替运行时恢复。
后续汇报必须分别列出现场恢复、防复发代码、测试、合并、部署和人工验收，不能再
以提示优化或某次恢复成功关闭根因问题。
