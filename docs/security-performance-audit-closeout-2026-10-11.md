# 2026-10-11 安全与性能审计结项

Status: closed for this audit round.

## 1. 背景与范围

对 `npcink-cloud-addon` 全量 `includes/` 做了一轮只读安全与性能审计
（双子代理深度审查 + 关键结论人工抽查 + `composer audit` +
PHPStan level 5 第二意见）。基线健康：无 Critical/High 安全发现，
依赖无已知漏洞，静态分析零错误。

本轮落地三个独立 PR：

| PR | 主题 | 状态 |
|----|------|------|
| #254 | `fix(security)`: portal 链接锚定到已验证 base URL（拒绝带 scheme / 协议相对路径；缓存键加 `portal-links-v2` 种子使部署前旧 transient 首读即 miss；卸载清理同步派生并顺带清理旧键） | 已合并 |
| #255 | `perf(i18n)`: AI 插件 JS 本地化 payload 限定到真实消费表面（`_page_ai-`、`options-connectors`、评论页、仪表盘、媒体库、文章编辑器），且每请求至多打印一次（修复存量双打印缺陷） | 已合并 |
| #256 | `perf(observability)`: 采集事件请求内零 I/O 累积，shutdown 单次有界持久化；flush 前先持久化保证同轮 cron 事件不晚点 | 见 PR |

ocr 顾问审查在这轮反复证明有效：#254 抓到旧缓存延续服务与卸载键
派生失同步两个真实缺陷；#255 第一轮就抓到评论审核/仪表盘小工具
这两个核心消费表面被允许清单遗漏（High 级回归）、编辑器双钩子
双打印（存量缺陷）、连接器页面遗漏、站点/小工具编辑器过度注入；
#256 抓到 shutdown 期间捕获丢失、同轮 cron 事件晚点、
`delete_data()` 无法清除内存 pending 三个边界。

## 2. 有意不做：entitlement 编辑器路径异步刷新（审计中危项 3）

审计建议"同步返回旧快照 + 后台异步刷新"。落地前三条硬约束均冲突：

1. **旧快照服务违反已测试的 fail-closed 契约。**
   `get_wordpress_ai_capabilities()` 在快照过期/刷新失败时将全部
   能力降级为 `unknown`，`behavior-entitlement-summary.php` 逐场景
   锁定该行为。向 `is_model_available()` 提供过期快照等于绕过
   "Cloud 能力失败即关闭"的安全姿态，属产品语义变更而非优化。
2. **后台异步需要超出边界文本的 cron 特许。** AGENTS 仅允许
   observability 上传与 Site Knowledge 投递两类 WP-Cron 用途；
   为 entitlement 缓存新增计划任务超出既有许可，需要明示批准。
3. **短超时方案收益被客户端下限钳制。** Runtime client 对所有
   请求强制 `max( 5, timeout )` 下限，交互路径超时上限 8→5 秒，
   边际收益不抵改动面。

现状评估：阻塞刷新每 5 分钟至多一次、仅一个请求，15 秒锁与
30 秒退避已防止风暴；设置页 AJAX 自刷新与验证流程持续续期快照，
编辑器路径阻塞是快照窗口之间的兜底而非常态。正确解法需要
跨仓契约变更——Cloud 在签名响应（如 `/v1/runtime/execute` 回包）
中携带能力快照，或平台明示批准 entitlement 刷新的 cron 特许；
在此之前维持现状，由设置页与验证流程承担续期。

## 3. 低优先级积压（等真实变更触及时顺手处理）

- 匿名前端请求固定成本：`plugins_loaded` 上两个 `sync_schedule()`
  触发设置读取 + sodium 解密（约 1-3 ms）。可选：门控到
  `is_admin() || wp_doing_cron()`。
- `sync_connected_marker()` 每次请求 `admin_init` 尝试写选项
  （同值 no-op 后仅剩一次读）。可选：仅在状态转换时写。
- `class-cloud-editor-assist-quality.php` 的 `expire_pending()` 与
  随后 `pending_records()` 重复读同一选项两次。可选：返回复用。
- 小时对账查询无 `post_modified_gmt` 索引（有界：每小时一次、
  `LIMIT 50`、游标防重扫）；仅当大站出现真实慢查询证据再评估。
- observability flush cron 无执行锁；服务端 `batch_idempotency_key`
  已幂等兜底，重叠仅浪费不损数据。
- Cloud 返回 `output_text` 无单字段长度上限（整包 1 MiB 上限兜底）；
  如 Cloud 契约增加字段上限，同步镜像到
  `class-cloud-runtime-request-guards.php` 的请求侧边界。
- 出站校验与实际请求两次独立 DNS 解析的 rebinding 残余
  （`class-cloud-outbound-policy.php` 内已文档化，HTTPS + 禁重定向
  + `wp_safe_remote_request` 缓解栈合理）。
- 仓库携带 `sj/` 等非插件目录（release manifest 已排除，仅源码
  仓库卫生）。

## 4. 流程备忘（本轮再次确认）

- ocr 输出必须整份落盘后再读文件（管道接 `tail` 截断、后台任务
  输出行数截断都发生过）；ocr 运行期间禁止切换工作树分支，否则
  其文件读取与 HEAD 漂移导致整轮作废。
- POT 行号零容忍再次应验：仅移动 `__()` 行号（无字符串变更）即
  阻塞 Release static gates；推送前最后一步固定
  `composer run i18n:refresh`。
- `required_conversation_resolution` 生效中：CI code-review 线程
  会阻塞 auto-merge，发布后需逐线程回复处置（采纳/婉拒+理由）并
  resolve；同建议可能在新推送上重复提出，引用前次处置即可。
