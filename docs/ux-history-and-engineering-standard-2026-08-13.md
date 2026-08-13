# Cloud Addon 用户体验问题复盘与工程规范

状态：Accepted（2026-08-13）

适用范围：`npcink-cloud-addon` 的设置页、连接流程、Site Knowledge 投递状态、Runtime Runs 只读投影、WordPress AI 固定 UI 本地化，以及这些区域的测试和文档。

## 1. 背景与结论

本轮工作从用户端视角复盘 Cloud Addon：用户看到的是一个连接器和状态摘要，而不是 Cloud 控制台、工作流引擎或 WordPress 写入工具。历史问题主要集中在四类错位：

1. 页面动作与实际注册入口不一致，导致独立安装导航失效。
2. “凭据已验证”“当前服务可用”“Site Knowledge 投递成功”等状态混在一起，造成错误安全感或误判。
3. 失败操作仍可能显示成功，或把底层网络错误直接暴露给普通管理员。
4. 移动端、本地化和数据披露没有跟随真实功能边界同步更新。

处理后的原则是：先建立正确的用户心智模型，再改善操作反馈，最后补充低频诊断和本地化；每一步都以边界合同和自动化测试作为护栏。

## 2. 历史问题与处理结果

### 已关闭的问题

- 独立安装页面的标签、子标签、Runtime Runs 链接和 GET 表单统一使用实际注册页面 URL。
- Portal 授权 state 改为点击时生成，避免页面渲染时产生过期 state。
- 新凭据先验证，验证失败时保留旧连接；同一连接重新验证仍保持原有保存语义。
- 加密存储预检失败时不发起网络请求，不覆盖原有连接。
- 权限写入失败时停止同步、Cron 和 marker 等副作用。
- 关闭 Site Knowledge delivery 时同步关闭 generation reference，避免隐藏权限继续生效。
- 将“Credentials / Last verification succeeded”和“Current service”拆成两个状态维度。
- AJAX 刷新显示后端具体错误，并保留可用的旧数据。
- Site Knowledge 投影 retrieval acceptance，并按 WordPress 站点时区显示时间。
- Site Knowledge 显示全量投递进度、下一次尝试和丢弃的 delivery notification 数量；明确丢弃的是通知，不是 WordPress 内容。
- Runtime Runs 只有在 Cloud 明确标记失败且可重试时才显示 Retry；只有结果可用时才显示 Read result。
- DNS、连接拒绝、超时、凭据失效等错误转换为管理员可执行的提示，原始错误留在技术详情。
- 782px、480px 和更窄屏幕的摘要、表格和 Site Knowledge 操作完成响应式处理。
- Site Knowledge 移动端保留“Request public content refresh”为主操作，索引管理和 Cloud 入口降为次级链接。
- 安装文档改为 Portal-first；手工 Base URL/API Key 仅作为高级恢复入口。
- README、设置页和隐私页明确四类数据发送：WordPress AI/runtime、Media、Site Knowledge、metadata-only Monitoring。
- 明确连接成功后 WordPress AI connector 默认启用，并说明可在 Overview 关闭。
- README 修正 Site Knowledge reference 的能力范围，避免承诺未实现的字段。
- Cloud Addon 固定 UI 中文翻译补齐；WordPress AI 上游插件审计中的固定 UI 候选从 38 项降为 0。

### 需要真实环境确认的事项

这些不是已知代码缺陷，而是依赖登录态、Cloud 服务状态或人工感知的验收：

- Portal 授权回跳、MFA、撤销授权、更换账户和站点冷却流程。
- 已登录 WordPress 中实际切换权限开关后的视觉、焦点和错误反馈。
- 320px/375px 中文页面的最终视觉密度、键盘操作和屏幕阅读器朗读顺序。
- 真实 Cloud 超时、DNS 失败、凭据失效和服务不可达场景。
- 线上隐私、条款和数据保留页面与仓库文档的同步。

## 3. 推荐的分阶段工作流

### 阶段 A：可靠性和状态真相

先修复会导致用户做出错误决定的问题：

- 检查 URL 是否指向真实注册页面。
- 检查保存失败是否会阻止后续副作用。
- 检查新凭据是否在验证前覆盖旧凭据。
- 将本地缓存状态、Cloud 返回状态和用户可见状态分层。

这一阶段必须先补行为测试，再改界面文案。

### 阶段 B：恢复路径和操作反馈

每个高风险操作都应有四个状态：

1. 可执行前提；
2. 提交中；
3. 成功；
4. 失败及下一步。

权限开关尤其要防止重复提交，并在重定向后回到原行。失败反馈应靠近产生问题的控件，而不是只放在页面顶部。

### 阶段 C：信息层级和窄屏

默认界面只保留当前任务所需事实。主按钮承担当前最重要动作，低频动作改为链接或折叠详情。移动端不是简单缩小桌面布局，而是重新排序：

```text
当前状态 → 主操作 → 次级链接 → 技术详情
```

### 阶段 D：本地化和数据披露

本地化必须以源码 POT 为准，并只翻译固定 UI。动态 ability 名称、描述、Schema、JSON 字段、provider/model ID 和 contract ID 由来源系统负责。

数据披露必须回答三个问题：发送什么、何时发送、谁负责保留和删除。Monitoring 的 metadata-only 限制不能被误写成所有 runtime 都不发送正文或 prompt。

## 4. Cloud Addon 用户体验规范

### 连接流程

- 主路径是 Cloud Portal authorization。
- 手工凭据只能位于折叠的恢复入口。
- 连接按钮旁说明账号归属、默认 connector 状态和隐私/保留信息。
- 授权 state 必须在点击时生成，并设置短期有效期。
- 连接回跳后立即验证；验证失败不得覆盖可用旧连接。

### 状态展示

至少区分：

| 维度 | 示例 | 来源 |
| --- | --- | --- |
| 凭据 | Last verification succeeded | 本地验证状态 |
| 当前服务 | Recently reachable / Needs attention | 本地缓存的 Cloud read 或 health snapshot |
| 投递 | Buffered / Delivering / Retry scheduled | 本地 bounded delivery buffer |
| Cloud 结果 | Retrieval acceptance passed | Cloud 只读投影 |

页面渲染不能为了“更新状态”直接发网络请求。刷新必须由明确的管理员动作或已有 AJAX 入口触发。

### 错误与恢复

- 普通文案描述原因和下一步。
- 原始 cURL、HTTP 或 Cloud 错误只进入折叠技术详情。
- 错误文案不得包含 secret、Authorization header 或完整凭据。
- 替换连接失败时明确说明“现有连接已保留”。

### Site Knowledge

- Addon 只拥有本地 delivery consent、bounded notification buffer 和管理员 delivery intent。
- Cloud 拥有索引执行、生命周期、freshness、重建/删除结果和诊断真相。
- 丢弃计数必须明确是 delivery notification 丢失，不是删除文章。
- “Request public content refresh”是面向管理员的恢复动作，不得被描述为本地索引重建。

### 本地权限交互

- 开关提交期间禁用控件并显示 Saving。
- 返回后显示当前行的成功或失败结果。
- 恢复焦点到反馈或原开关。
- 无 JavaScript 时保留可提交按钮。
- 关闭上游能力时同步清理依赖它的下游权限。

## 5. 边界和安全护栏

Cloud Addon 仍是 Cloud connector，不得变成第二控制面。任何新改动都必须检查：

- 不恢复 `/v1/runtime/workflows/runs`。
- 不添加 router、prompt、preset、approval、proposal、workflow/task queue、scheduler truth、billing truth 或 WordPress write ownership。
- 不在 UI 拆分展示 `site_id`、`key_id`、`secret`。
- 不打印或记录 stored secret。
- 不调用 WordPress 写 API。
- Site Knowledge 不在本地拥有索引真相或 freshness policy。
- 监控只上传明确允许的 metadata-only 字段。

## 6. 测试与交付矩阵

代码交付前至少运行：

```bash
composer run test:all
composer run check:js
composer run check:wporg
composer run i18n:check
composer run ai:i18n:audit
composer run smoke:playground
git diff --check
rg '/v1/runtime/workflows/runs|\\b(?:wp_insert_post|wp_update_post|wp_insert_attachment|wp_update_attachment_metadata|update_post_meta|wp_set_post_terms|set_post_thumbnail|media_handle_sideload)\\s*\\(' --glob '*.php' --glob '!build/**' .
```

影响设置页、默认连接状态或插件 bootstrap 的改动必须运行 Playground smoke。影响固定 UI 的改动必须更新行为测试和 POT/PO/MO。影响公共合同的改动必须更新跨仓库矩阵记录。

浏览器验收应覆盖：

- 已验证和未验证两种入口；
- Portal 新标签授权按钮；
- 独立安装 `options-general.php` 地址；
- 1280px、782px、375px、320px；
- 中文 locale；
- 键盘 Tab、Enter、Space、焦点恢复；
- 保存成功、保存失败和 Cloud 暂时不可达。

## 7. 后续开发检查表

提交前回答：

- 用户现在能否准确知道“已验证”代表什么？
- 失败后是否知道下一步？
- 主操作是否唯一且在窄屏仍清晰？
- 页面是否在 render 阶段保持无网络副作用？
- 是否把 Cloud 真相误投影成本地真相？
- 新字符串是否属于固定 UI，是否已进入 POT/PO/MO？
- 是否增加了与功能边界匹配的行为测试？
- 是否验证了 secret、WordPress 写 API 和退役 endpoint 没有泄露或恢复？

这份规范与 `docs/admin-surface-standard.md`、`docs/ai-plugin-localization-maintenance.md`、`docs/cloud-addon-complexity-budget.md` 和 `AGENTS.md` 一起使用。若新需求与这些边界冲突，应先写决策记录，再实现代码。
