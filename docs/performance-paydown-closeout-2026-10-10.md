# 性能清偿收口（每请求固定开销）

状态：Accepted（2026-10-10）

适用范围：`npcink-cloud-addon` 的每请求固定开销与 cron 侧重复计算。
本文记录 2026-10-10 安全/性能体检的结论与当日落地（PR #252，
squash `bfb0b00`），并把可复用的模式与流程教训固化为规范。

## 1. 体检结论

安全面零高危/中危：全部 `admin_post_*`/`wp_ajax_*` 处理器均有
nonce + `manage_options`；输出转义完整；凭证信封加密存储且全链路
脱敏；出站策略强制 HTTPS、禁重定向、私网 IP 拒绝；媒体路径有
realpath 围栏与 sha256 + 签名头双重校验；全仓库无
`unserialize`/`eval`/shell 调用。两个 low 备忘（OAuth state 消费
非原子、DNS rebinding 残余）维持文档化接受，不加复杂度。

性能面发现集中在"每个请求白付一次"的固定开销与 cron 侧重复构建，
全部当日落地：

| 处置 | 落点 |
| --- | --- |
| i18n 映射每请求构建一次 | 两个 zh_CN shim 类的 `translations()` 加 static 缓存（此前每次 gettext 调用重建约 660 条数组） |
| 设置解密每请求一次 | `Npcink_Cloud_Addon_Settings::get_settings()` 请求级缓存 + 四路失效（写方法、选项钩子、`switch_blog`、`Cleanup::delete_all`） |
| 监控关闭稳态零清理 | `sync_schedule()` 禁用分支只在 `wp_next_scheduled()` 仍为真（即开→关转换时刻）才执行 `delete_option` |
| 清理标记 autoload | `..._cleanup_0_2_0` 标记改 autoload 写入，含对 ≤0.4.0 非autoload 旧行的一次性删除重写迁移 |
| flush 单次运行文档复用 | `post_document()` 按请求内 memo 共享"发前指纹"与"发送载荷"两处构建；投递后校验前强制清 memo 保新鲜度语义 |

新增三条性能护栏：监控关闭稳态零清理、转换时刻正确清理、重复
`get_settings()` 零选项读。

## 2. 经验（固化为规范）

1. **请求级缓存的标准失效集。** 给含敏感数据的读取加 per-request
   static 缓存时，失效必须同时覆盖：本类写方法（显式调用）、外部
   WordPress-API 写入（`update_option_{name}` / `add_option_{name}` /
   `deleted_option` 钩子）、多站点 `switch_blog`、以及绕过本类 API
   的同进程删除方（如 `Cleanup::delete_all`）。#252 的顾问审查抓到
   漏掉 `switch_blog` 会导致多站点循环中用 A 站凭证签 B 站请求——
   属真实缺陷而非理论洁癖。
2. **"只在转换时付费"优于"每请求自愈"。** 禁用态的清理逻辑挂在
   每个请求上是隐性税；用可观测的转换信号（如 `wp_next_scheduled`
   仍为真）把清理限定在状态切换那一刻，稳态即零成本。护栏测试要
   同时锁住稳态（零开销）与转换（正确清理）两个面。
3. **`update_option` 同值不重写 autoload 列。** 靠"值不变"的旧行
   不会因新代码传了 `autoload=true` 而升级；需要迁移时用
   `wp_load_alloptions()` 探测旧行状态，一次性删除重写。
4. **子代理审计的量化结论必须抽查核实。** 本轮子代理报告
   "指纹重算最多 500 条缓冲"，核实后实为仅对已尝试批次（≤25 条）
   重算——直接按报告数字行动会夸大问题规模。凡带数字/量级的发现，
   落码前先读源码确认。
5. **`ocr review` 输出必须整份落盘。** 管道接 `tail` 会静默截掉
   前面的发现（本轮首跑丢了 2 条真问题，复审完整捕获才拿到全部
   3 条）。规范：`ocr review ... > /tmp/x.log 2>&1` 再读文件。
6. **行为测试共享进程，静态缓存要进 reset。** `tests/run.php` 用
   `require` 串起全部行为文件，类静态属性跨文件存活；新增请求级
   缓存必须挂进 `maca_reset_test_state()`，且测试里裸写
   `$GLOBALS['maca_options']` 后要显式调失效方法（裸写不走
   `update_option` 钩子，钩子才是生产失效路径）。
7. **POT 新鲜度门禁对行号偏移零容忍（再次确认）。** 即使是行为
   无关的性能重构（加属性、归一化缩进），只要动了含 `__()` 文件的
   行号就会阻塞 Release static gates。规范：推送前最后一步固定执行
   `composer run i18n:refresh`。

## 3. 已知非阻断事项（有意不做）

- observability 单轮 5 次 editor-feedback 发送上限维持不变：最坏
  情况仅在缓冲区满时出现，重试设计已有界；待出现真实漏跑证据再收紧。
- 小时对账的无条件状态调用 + 1000 ID 扫描属文档化边界行为
  （hourly reconciliation contract），维持原样。
- 凭据"延迟解密"加固（仅缓存非敏感字段、远端请求时才解密）经
   PR #252 inline disposition 记录为可选未来项，受复杂度预算约束
   暂不做；现有明文驻留窗口与改动前等价（Runtime Client 实例本就
   持有副本），外泄路径均已由脱敏层覆盖。
- GlotPress zh_CN 待审翻译（约 420 条 Waiting）与 Phase-3 审批
  为常备积压，与本轮无关，见
  `docs/wordpress-org-release-and-translation-log.md`。
