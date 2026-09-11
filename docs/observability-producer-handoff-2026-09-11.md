# 插件上报接通与验证交接（2026-09-11）

Status: dated implementation evidence and connector maintenance guidance;
not production installation or human acceptance.

## 改动与边界

原收集器只监听 `npcink_observability_event`，而三个生产者使用自己的钩子，
导致直接调用收集器的测试通过，却不能证明生产者事件真的进入上报链路。
现在将三个钩子接入同一个 `capture_event`，保留通用钩子：

| 生产者钩子 | 记录来源 |
| --- | --- |
| `npcink_abilities_toolkit_observability_event` | `npcink-abilities-toolkit` |
| `npcink_governance_core_observability_event` | `npcink-governance-core` |
| `npcink_openclaw_adapter_observability_event` | `npcink-ai-client-adapter` |

仍要求连接已验证且站点已同意监控；元数据白名单、有界缓冲、签名传输和原定时
发送机制不变。不收集提示词、生成结果或凭据，不新增调度、审批或 WordPress 写入权。

## 维护时采用的验证顺序

1. 核对真实生产者的钩子名与事件字段，不根据产品显示名猜钩子。
2. 测试从已注册监听器派发，覆盖连接验证与监控同意的四种组合。
3. 检查允许情况下事件进入缓冲、发送保持来源及诊断字段、私密字段被排除。
4. 用真实站点验证生产者、缓冲、签名发送和 Cloud 只读摘要；分别记录每段证据。
5. 区分技术验证、自然使用、人工接受和正式安装。未发现记录不等于未使用，
   缓冲为空也不能单独证明服务端已接收。

本次行为测试和跨仓库矩阵中的五个 WordPress 项目检查通过。本地
`magick-ai.local` 使用当前 Addon 工作区并连接 18010；三个技术生产者事件已
验证送达，随后签名摘要可见这三个来源及 Addon 自身记录，待发队列为空。
验证事件明确标为 `validation.technical_monitoring_only`，不当作真实任务。

本记录提交前基础版本为 `46ffb0e` 加本批增量；合并以 PR 和当前主干为准。
没有发布 WordPress.org 或安装正式站点；没有付费模型调用。原浏览器交互与
真实文章试用交由操作者完成，不从收集器测试推断这些结果。

## 回退与后续

如接通后出现传输回归，按仓库流程回退本次钩子订阅改动；不要删除用户的监控
同意或已有诊断记录。正式发布按现有包与发布检查执行；这份记录不授权发布。
