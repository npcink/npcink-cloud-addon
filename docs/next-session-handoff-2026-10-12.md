# 2026-10-12 会话交接：用户端体验排查与 UX 还清（PR #258）

状态：**本轮代码已全部合并**（PR #258，squash `b6db950`，master 最新）；本文档是
面向下一阶段的交接记录，随本 docs PR 发布。可选的规范沉淀（closeout 文档）
见待办 A-1，与本文档不重复。

## 一、本轮会话做了什么

1. 从管理员 / 编辑 / 访客三类用户视角做了全量体验排查（三路只读扫描 + 抽查验证），
   产出问题清单与建议，用户确认"依次落实"。
2. 按建议落地了 6 项（详见下节），另 2 项评估后搁置（理由记录在 PR #258 body）。
3. 全量门禁绿：`test:all`（含 `check:boundary`）、`git diff --check`、PHPStan
   level 5、`composer validate`、`check:wporg`、`smoke:playground`（触及凭证状态
   语义，必跑项）、`i18n:pot`/`update-po`/`i18n:check`（608/608 zh_CN 翻译完整）。
4. `ocr review` 3 条低级发现（两个模型请求日志消息门控、shim 测试覆盖缺口）已
   修复并随同一提交合入。CI 检查全绿后 squash 自动合并完成。

## 二、已合并改动明细（PR #258）

| # | 改动 | 关键位置 |
|---|------|----------|
| 1 | 连接器开关欺骗性文案修正。**方向说明**：排查初判"缺渲染"，读标准后反转——`admin-surface-standard.md` 明文 "connector state is not presented as another permission"，开关不渲染是有意设计，缺陷只在两处承诺文案 | `includes/class-cloud-settings-page.php:1333`（Connect 面板）；`class-cloud-settings-actions.php` `get_local_permission_definitions()` |
| 2 | 首次连接探针失败不再丢弃已交换凭证：无已验证连接时凭证以未验证态落库（复用 `handle_complete_auth` 激活挂起分支模式），"检查连接"即可重试、无需重走 Portal；"已保留现有连接"文案只在真有已验证连接时出现 | `class-cloud-settings-actions.php` `persist_and_verify_settings()`；测试 `tests/behavior-credential-store.php` |
| 3 | 授权交换网络错误补脱敏 + 200 字符截断（此前唯一漏网路径） | `exchange_authorization_code()` |
| 4 | zh_CN 兜底 shim 守卫放宽到 REST/AJAX（编辑器走 REST，此前中英割裂）；纯前台仍排除 | `includes/class-cloud-addon-localization.php` `should_localize()`；测试 `behavior-cloud-addon-localization.php` |
| 5 | queued/running 信封不再伪装成"无输出，请重试"：三模型共用新助手 `response_indicates_in_progress_run()`，用户文案、evidence `quality_reason`、journey 失败码、请求日志 validation_error 四层一致门控为 `runtime_run_in_progress` 等待提示 | `class-cloud-wordpress-ai-connector.php`（新稳定码 + 助手）；text / vision / image 三个 model；测试 `behavior-wordpress-ai-connector-result.php` |
| 6 | Overview 首屏真实性：Connected 徽章随健康快照降级为琥珀色（`--warning` 徽章样式此前根本不存在，摘要区告警徽章一直裸奔，一并补上）；监控 / 知识库 attention 行分别加"查看上传详情""查看站点知识库状态"链接 | `assets/admin.css`；`class-cloud-settings-page.php` Overview 徽章与 notices 区 |

附带：5 个文件的行数棘轮上调并同步 `docs/cloud-addon-complexity-budget.md`
（settings-actions 1131、connector 1024、image-model 492、text-model 390、
vision-text-model 126）；6 条新串进入 shim + POT/po/mo。

## 三、评估后搁置的契约级事项（勿在本仓库单方面重启）

这两项**不要**在 addon 侧单独动手，重启前先在 Cloud 仓库 / 契约层立项：

1. **图片交付全有全无**（要 4 张第 3 张失败 → 前 2 张已 ACK 的也整体丢弃，重复计费）。
   搁置理由：严格 ACK 握手被治理测试钉死（`behavior-wordpress-ai-connector-result.php`
   要求畸形 ACK 必须抛错）；AI 客户端结果 DTO 无部分成功通道，静默少交付反而诱导
   编辑重复尝试。真正的修复是 **artifact 重取/续传通道**，属运行时契约设计。
2. **能力快照 stale-while-revalidate**（Cloud 抖动 5 分钟 → 编辑器模型无征兆消失）。
   搁置理由：`max_age_seconds ≤ 300` 是 Cloud 契约钉定的准入时效（强校验），超窗
   续用 configured 态是 fail-open；与 #254-#257 轮搁置的 entitlement 异步刷新同族，
   需 Cloud 侧对齐。

## 四、待办清单

### A. 下一阶段建议先做（本仓库可独立完成、成本低）

- [ ] **本轮 closeout 文档 PR**（仓库惯例）：可新增
      `docs/user-facing-ux-paydown-closeout-2026-10-12.md`，参照
      `security-performance-audit-closeout-2026-10-11.md` 的结构沉淀本轮
      规范（如"文案不得承诺 UI 没有的控件"）。
- [ ] **死锚点清理**：`class-cloud-settings-page.php` 的
      `render_site_knowledge_article_coverage` 渲染隐藏空 span（~1550 行），
      `assets/admin-site-knowledge.js` 查询的
      `[data-npcink-site-knowledge-coverage-refresh]` 在 PHP 无对应元素。
- [ ] **断开连接的无 JS 回退确认**：当前确认完全依赖内联 `onclick confirm()`，
      无 JS 时一键直提交（`render_disconnect_form`）。
- [ ] **`getModelMetadata` 未翻译异常**：抛
      `'Npcink Cloud model metadata not found.'` 未包 `__()`
      （`class-cloud-wordpress-ai-model-metadata-directory.php`）。
- [ ] **artifact 过期未 purge 的分类小修**：`expires_at` 过期但无 `purged_at`
      时落进含糊的 `artifact_contract_invalid`，应走更准确的
      `artifact_expired`（`class-cloud-wordpress-ai-image-model.php` 大条件分支）。
- [ ] **22 条任务合同英文校验串**：`class-cloud-ai-task-contract.php` 的
      `self::error()` 消息未本地化，会以 160 字符英文尾巴透给编辑（正常请求
      不触发，属打磨项）。
- [ ] **readme 法务链接 locale 感知**：中文用户点条款落到
      `cloud.npc.ink/terms/en/...` 英文页；若存在 `/zh/` 版本可按 locale 选链。

### B. 需要"安静标准"权衡后再动（低危 UX 尾项）

- 通知 / 权限反馈 transient 60 秒 TTL（慢加载可能吞掉结果提示）——延长 TTL 与
  "安静"标准无冲突，但需注意 transient 清理路径。
- 权限开关"半 AJAX"（显示 Saving… 后仍整页刷新，丢滚动/展开状态）——改真 AJAX
  需评估与 `admin-permissions.js` 焦点回落、noscript 回退的兼容。
- 凭证在 Cloud 侧被吊销时本地 `is_verified()` 永不降级（无后台 401/403 检测），
  Overview 绿到缓存耗尽；恢复路径深（Advanced）。本轮已把徽章与 attention
  链接补齐，自动降级若要做需设计"有持久化失败事实才降级"的标准符合版。
- entitlement 过期缓存首屏仍显示 "Recently reachable"，靠 JS 加载后纠正
  （`get_current_service_health` 只看 `available` 不看 `stale`）。
- 图片参数静默纠偏无提示：不支持的比例静默回退 1:1、数量钳到 1..4
  （`class-cloud-runtime-request-guards.php`）——提示通道受"addon 不 patch
  AI 插件 UI"边界约束，只能经结果 metadata，需先评估。
- 授权新标签打开后原页面无"等待授权中"状态（标准允许现状，改动收益低）。
- readiness "Next step: %1$s (handled by %2$s)" 工单式文案（Advanced 内，可接受）。

### C. 长期 / 跨仓库线程（继承自 backlog，非本轮产生）

- **GlotPress zh_CN Phase-3**：本插件自己的 ~420 Waiting/set 未审批、readme
  45 条未翻译——用户侧人工操作，模板已在翻译日志。
- **上游 AI 插件 shim 贡献**：GlotPress `wp-plugins/ai` zh_CN 集为空，659 条
  shim 串是现成贡献集；建议走 make.wordpress.org/polyglots 或 #zh-cn 的
  PO 导入而非逐条提交。
- **provider-acceptance 缺口**：0.4.0 发布时 `acceptance:wp-ai-provider` 0/14
  provider_unavailable（开发 Cloud 文本供应商池宕），供应商恢复后重跑。
- **复杂度债务（机会主义）**：PHPStan baseline 收缩、跨文件重复
  （strict_timestamp 对、MAX_IMAGE_BYTES、delivery-ack 平行块）、settings-page
  disclosure 折叠；editor-feedback 发送上限等真实漏投递证据。

## 五、验证门禁速查（新会话动手前先读）

必读标准：`AGENTS.md`、`docs/admin-surface-standard.md`、
`docs/admin-simplification-and-delivery-engineering-standard-2026-08-25.md`、
`docs/cloud-addon-complexity-budget.md`、`docs/verification-evidence-standard.md`。

```bash
git status --short --branch        # 变更信封起点
composer run test:all             # 含 check:boundary
git diff --check
composer run stan                 # advisory，level 5，勿扩 baseline
composer i18n:pot && composer i18n:update-po && composer i18n:check   # 每次 amend 后必跑
ocr review --from origin/master --to HEAD   # advisory，一次一个，冻结分支再跑
composer run smoke:playground      # 触及 bootstrap/激活/公共 API/默认凭证态时必跑
composer check:wporg
```

发布：准备 body 文件（模板 `.github/pull_request_template.md`，**放在 /tmp，
仓库内会让 publish 拒绝执行**），然后
`composer pr:publish -- --title "<title>" --body-file /tmp/xx.md`。
合并后核对完整检查汇总（scope 相关的 advisory 失败也要处理）。

## 六、本轮新踩坑速记（新会话避免重复）

- 带大括号 `namespace { ... }` 的行为测试文件，**闭合大括号后不能追加任何代码**
  （`php -l` 直接 fatal），新 `maca_assert` 块要插进块内末尾。
- `publish-pr.sh` 要求 `git status --porcelain` 完全干净 → PR body 文件放仓库外。
- 排除类断言勿自相矛盾：不能同时断言"翻译生效"与"守卫关闭"——排除场景应断言
  原文透传不变。
- 行数棘轮上调必须三处同步：代码事实 + `tests/static-contracts.php` 榜单 +
  `docs/cloud-addon-complexity-budget.md`（含上调理由）。
- 修复 ocr 发现后文件行数会变 → 最终以最终行数定棘轮，别提前定。

## 七、仓库状态

- 分支 `master` @ `b6db950`（= origin/master），工作区干净；本文档由本 PR 引入。
- 会话记忆（memory）已更新：`deferred-work-backlog`（#258 + 两条契约级搁置 +
  低危尾项）、`pitfalls-learned`（本轮 (g)(h)(i) 三条）、`MEMORY.md` 索引。
