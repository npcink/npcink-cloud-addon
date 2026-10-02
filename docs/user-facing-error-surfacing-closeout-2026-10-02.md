# 用户可见错误呈现与恢复信号收口

状态：Accepted（2026-10-02）

适用范围：`npcink-cloud-addon` 的 WordPress AI 连接器错误文案、
设置页概览与 Site Knowledge 恢复行、监控状态信号、admin JS 行为、
zh_CN 本地化维护。

当前界面标准以 `docs/admin-surface-standard.md` 和
`docs/admin-simplification-and-delivery-engineering-standard-2026-08-25.md`
为准。本文记录本次用户端体验排查的结论、落地内容与需要长期遵守的
工程规则。

## 1. 排查结论

设置页渲染层的基础是健康的：文案全部走翻译函数、健康态安静、
渲染期零 Cloud 请求、zh_CN 目录全覆盖。本次问题集中在三条路径：

1. 编辑器 AI 失败路径：连接器把内部错误码、合同术语、上游校验原文
   （最多 4096 字符）直接拼进 `RuntimeException`，未翻译、无下一步，
   且预转义与 WordPress AI 客户端的渲染转义叠加，用户会看到
   `&#039;` 这类 HTML 实体。
2. 静默失败：投递重试耗尽后被丢弃的内容变更只写入了
   `dropped_count`，没有任何 UI 读取；对账游标在入缓冲时已前移，
   丢弃内容不会被小时对账重放，属于真实数据缺口。
3. 信号错位：监控被用户主动关闭被记为"上传错误"并触发
   "需要关注"；分类码、readiness "Owner/Next safe action" 等
   运维工单式文案直接面向站长。

## 2. 落地内容

- 连接器新增 `user_facing_connector_error()`（稳定码 → 友好文案 +
  下一步，括号内保留稳定码）与 `user_facing_runtime_failure()`
  （按未授权/限额/不可达/已过期/通用归类），28 处异常全部改走映射，
  详情截断 160 字符；移除异常消息中的预转义。
- `dropped_count > 0` 时 Site Knowledge 概览与标签页出现 attention
  行并复用"更新知识库"恢复入口；全量投递完成（所有公开内容已重投）
  时清零并自动隐藏入口。
- 监控关闭不再写 `last_upload_error`（保留历史事实，清除错误信号），
  未验证保持精确错误文案；attention 判定增加 `enabled` 门控。
- 保存时站点未激活、授权过期措辞、readiness 文案、分类码行、
  cURL 原文截断、知识库刷新失败文案框架化等一致性修复。
- Site Knowledge 重试改为原地更新等待计数，替代无提示整页刷新；
  浏览器网络错误原文不再作为状态文案。
- 新增激活引导：插件列表页与仪表盘各一条可忽略（nonce + 每用户）
  的连接提示，连接成功自动消失，卸载清理用户 meta。
- zh_CN：36 条新串补齐 .po（602/602 全翻译）与硬编码 shim；
  清理 21 条无渲染点的 shim 死词条（保留一对复数词条作为
  ngettext 行为契约夹具）。

## 3. 工程规则

以下规则为本次收口沉淀，后续变更必须遵守：

1. **运行时错误必须走稳定码映射**。面向编辑器/用户的异常只能通过
   `user_facing_connector_error()` 产生：友好句子在前、稳定码在
   括号内、上游原文只做截断详情。禁止裸字符串 `RuntimeException`、
   禁止在异常消息里预转义（客户端渲染时统一转义）。静态契约
   `tests/static-contracts.php` 已强制。
2. **持久化失败事实 → 单一恢复入口 → 恢复后自动隐藏**。任何"数据
   缺口"类失败（如丢弃计数）必须满足 2026-08-25 标准的恢复入口
   五条件，且要有明确的"事实清零点"（本次为全量投递完成），
   否则 attention 行会永远无法回到健康态。
3. **本地主动选择不是故障**。用户关闭监控/投递是 opt-out，不得写
   错误字段、不得触发 attention 行；判定"需要关注"必须同时检查
   功能开关状态。混合原因的文案（"关闭或未验证"）必须拆开，
   每种原因一句明确的话。
4. **上游原文只做截断详情**。Cloud 校验错误、cURL 原文等允许出现
   在文案尾部，但显示层必须限长（编辑器 160、管理页 200 字符），
   正文永远是可行动的友好句子。恢复承诺必须与错误码一致：
   只有 `*_retry_scheduled` 可以说"会自动重试"；`delivery_attempts_exhausted`
   与 `full_index_delivery_blocked` 必须明说重试已停止并指向恢复动作，
   未知码不得做任何重试承诺。
5. **契约优先于改进**。与 `tests/static-contracts.php` 断言相悖的
   "合理改进"（例：`load_plugin_textdomain` 被 wp.org 审核惯例契约
   禁止）不得直接改代码绕过；如需改变决策，先立独立 PR 修改契约
   并记录理由。
6. **测试标记串跟随源码演进**。静态契约用源码子串做标记（异常
   文案、JS 守卫、标题标记）。改动这些位置时必须同步更新契约标记，
   并优先用新的稳定码（如 `cloud_wp_ai_image_delivery_ack_invalid`）
   作为标记，而不是英文句子。
7. **新增用户可见字符串的 i18n 流程**：新增 `__()` 源串 →
   `composer run i18n:refresh` → 在 zh_CN `.po` 补全翻译 →
   同步 `Npcink_Cloud_Addon_Localization` 硬编码 shim →
   `composer run i18n:check` 必须通过。注意 `update-po` 会重排文件，
   空翻译（含 Author URI）会被"无空翻译"契约拦截。
8. **JS 原地更新的渲染目标必须始终存在**。服务端按条件渲染的
   元素（如等待计数 span）一旦在初始状态为空就不渲染，后续
   AJAX 原地更新将丢失目标。初始为空时也要输出带 `hidden` 的
   元素，由 JS 负责显隐。

## 4. 已知未做项

- `load_plugin_textdomain`：被静态契约禁止（wp.org 审核惯例），
  随包分发的 `.mo` 在任何安装都不会被加载；非 wp.org 渠道的
  zh_CN 兜底由硬编码 shim 承担。如需改变，先修契约再实现。
- WordPress AI 插件 zh_CN 兼容 shim 对上游 `ai` 域的无条件覆盖：
  属既有设计，由 `composer run ai:i18n:audit` 流程持续审计，
  本次未改动。
- 请求日志写入失败新增了 `error_log` 兜底痕迹，但未建立本地替代
  日志存储（避免为诊断扩展产品面）。
