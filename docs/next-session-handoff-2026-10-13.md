# 2026-10-13 当前交接：0.4.1 候选与跨仓收口

> 2026-10-14 更新：本文件保留历史排查与当时快照；当前状态和包证据以
> [最终发布记录](041-final-release-record-2026-10-14.md)为准。刷新与恢复修复
> 已通过 PR #271 合并，主仓 `master@6db6638` 干净。下文旧包哈希、未提交状态、
> 脏工作区和历史投递计数不代表当前状态，也不能用于发布。

状态：Addon 0.4.1 基础候选已合并（PR #267）；后续权益刷新修复与证据由独立
`codex/041-refresh-closeout` 分支记录，PR/CI 状态以关联 GitHub 记录为准。WordPress.org SVN
发布和跨仓质量矩阵仍未执行。

## 当前已确认事实

- 本轮隔离工作树干净；主工作区的并行修改由另一聊天处理。
- 版本头、`NPCINK_CLOUD_ADDON_VERSION`、`readme.txt` 和本地语言目录均为
  `0.4.1`。
- 候选包 SHA256 为
  `5ac5bc5287d6eb6eb2bc4d18ed1998637287c2df7e6e1f92e79f5347351594a7`。
- 0.4.1 尚无 Git tag，也没有提交 WordPress.org SVN；WordPress.org 当前
  稳定版本仍为 0.4.0。
- 候选代码门禁、CI、Release static gates、PHP 版本矩阵和 WordPress AI
  兼容性矩阵均已通过。OCR advisory 曾因额度不足返回 429；恢复后的原候选代码审查返回两项发现，
  处置和本轮刷新回归见下节，修复差异另行审查。
- GlotPress 2026-10-12 的 stable/dev 插件和 readme 中文集合均已显示完整，
  但 0.4.1 发布后的语言包生成和目录缓存传播尚未验证。
- 本地 wp-admin 的中文概览、Site Knowledge 和连接管理页面已检查。本次
  `127.0.0.1:18010` 已恢复，后端 `e6e2a07f4870`、源码干净；连接成功反馈和
  无效 URL 失败反馈均在刷新后一次性消失。当前权益缓存 fresh，但 Site
  Knowledge 仍有 11 条放弃、782 条待处理，监控仍有 200 条待发，尚未声称恢复。
- 本地站点级语言文件日期为 2026-07-02，缺少两条手动备用说明；打包 PO 已有
  中文翻译。发布后应验证官方语言包更新，不能把此处英文归因于缺少源码翻译。

## 下一步顺序

1. OCR 已返回：修复权益刷新后残留 stale 警告；MO 未生成的发现经二进制核验为误报。
   OCR 发现处置和候选包核对已完成，发布前检查关联 PR 的完整 CI；旧包不得发布。
2. 获得单独的 WordPress.org 发布确认后，用临时 SVN 工作副本上传 trunk 和
   `tags/0.4.1`，核对 API、下载包、稳定版本、中文语言包和目录缓存。
3. 待所有参与仓库的工作树进入干净窗口并获得用户确认后，从
   `/Users/muze/gitee/npcink-workflow-toolbox` 运行
   `composer quality:matrix:run`。
4. Site Knowledge 真失败继续优先于 stale 标签；单独 stale、失败和缺失状态的浏览器案例已在隔离环境
   呈现，没有为了截图清除真实失败。Cloud 索引恢复可能涉及 Provider
   工作，应按预算另行确认；可选 vision、image 和评论验收继续延期。
5. 发布之外独立处理 Dependabot #260、#261、#262，并核对每个 PR 的完整
   检查结果；不把依赖升级混进已冻结的 0.4.1 包。

矩阵只读检查（2026-10-13T01:58:33Z）：Toolbox 已干净，但 Toolkit 仍有
未跟踪交接文档，Core 有一份已修改文档，Adapter 在
`codex/0-4-2-limit-validation` 有九个已修改文件。Addon、Eval Lab 和 Cloud
工作树干净。各仓库状态在变化，执行矩阵前必须重新读取分支、SHA 和状态；
本次没有运行跨仓 gates，也没有修改这些仓库。

## 暂不处理

- 不删除或提交 Toolkit、Adapter 的未跟踪交接文档。
- 不在 Addon 内实现图片续传/部分成功或 capability stale-while-revalidate；
  这些事项由 Cloud 或契约仓库负责。
- B 类 AJAX 保存、凭证吊销自动降级、图片参数提示和上游 AI 插件 shim
  贡献不进入 0.4.1 发布动作。

## 发布后回滚点

若 0.4.1 出现问题，使用后续补丁版本修复；不删除已发布 SVN 版本。候选阶段
仍可按 PR #267 粒度回滚，无需数据迁移。

## OCR 发现处置与刷新回归（2026-10-13）

原候选代码审查：`aacce25..9ae7e6a`，session
`866da943-0798-4a52-8f26-c65286516ab4`，8 个文件、2 项发现。

- medium 有效：成功的权益自动刷新原来只更新套餐/额度，顶部 stale 卡和服务
  徽章仍残留。修复在原页面标记权益自身的状态节点，仅在 AJAX 返回 fresh
  后隐藏该卡、更新徽章和已翻译的标签；不扩展 AJAX 返回契约。
- high 误报：Python `gettext.GNUTranslations` 直接读取已提交的 MO，确认
  `Not recently checked` 与完整 stale 消息都有中文；原候选 PR 已更新 MO。
  本轮复用现有翻译串，仅刷新 POT/PO 引用行号，MO 内容保持一致。
- PHP 渲染回归验证首屏警告与零 Cloud 读取；JS 回归验证 fresh、刷新失败、
  stale 响应和真实投递失败保护。刷新不能表示知识库恢复或索引完成。

浏览器证据位于忽略目录 `build/release-evidence-2026-10-13/`：
`refresh-stale-before.png`、`refresh-fresh-after.png`、`refresh-failure.png`、
`refresh-dropped.png`、`refresh-delivery-error.png`、`refresh-error-code-only.png`、`refresh-missing-state.png`、`refresh-live-overview.png`。
隔离页由 Local WordPress 的隔离分支 PHP 私有渲染方法生成（仅在临时脚本替换类名，避免与已加载
的插件类冲突，权限操作反馈使用空的隔离 stub，避免消费真实站点反馈），使用打包 zh_CN MO、
生产 CSS/JS 和显式模拟的 Cloud 响应；没有持久化状态修改或 Provider 调用。
真实 wp-admin 概览另行检查，仍显示需要注意，不把隔离测试当作 Cloud 验收。

浏览器回执：页面模型为 connector summary/detail，环境为 Local MySQL +
隔离静态 fixture，实际 viewport 为 1265×744（仓库未声明 PC viewport）。
规则 1/3/4/5/7/11：pass；规则 2：not_applicable（无仓库尺寸契约）；
规则 6/8/9/10：not_applicable（本次不改动作布局、选择/焦点或对话框）；
规则 12：隔离页无外部请求，六例日志无 error/warn，真实后台日志也为空，pass。
截图扫描：过期态警告在顶部，fresh 态隐藏后工作区上移，文字与徽章一致，pass。
M4、生产验收和人类视觉接受均 unmeasured，不声称已完成。

本次 Playground not_applicable：只改后台状态投影及其原有刷新行为，不触及
bootstrap、激活、默认凭据、公共 API 或兼容基线；引用 2026-10-10 Tier B。

隔离交付工作树：`/Users/muze/.codex/worktrees/041-refresh-closeout/npcink-cloud-addon`。
原工作区的恢复按钮及样式改动由另一聊天负责，用户明确要求本 PR 不纳入。
本轮只运行只读 `composer quality:matrix`；其他仓库及原工作区仍有改动，
没有运行跨仓矩阵门禁。监控在 2026-10-13T02:21:21Z 只读检查中已显示最近
上传成功、错误为空；Site Knowledge 仍有 11 条 dropped，不声称已全部恢复。

隔离分支重新通过 composer validate、test:all、PHPStan 和完整 release:verify
（含 WordPress.org、JS 15/15、i18n、50 文件包验证及 strict Plugin Check）。
旧候选包哈希已作废；以上新哈希才是本轮权益刷新修复的候选包。

首轮修复 OCR session `3abcc533-7b2e-4acc-842a-e6d6e14a9616` 有四项发现，
其中一个组触及设置的 token 上限，属于部分审查，不能记为全绿：

- 补齐 error-code-only 的真实知识库失败优先级，并增加 PHP/浏览器回归。
- 缺失 fresh 标签数据时避免渲染 `undefined`；简化测试的嵌套三元表达式。
- 不采纳保留健康状态顶部连接卡的建议：`admin-surface-standard.md` 明确该卡
  仅用于未验证/异常态，健康态只保留概览紧凑行；`render_connection_summary()`
  在健康 verified 状态本来就直接 return。低频连接管理仍在高级页。移除已经
  恢复的异常卡符合既定行为，并不移除永久的连接管理入口。

第二轮 OCR session `6b8eb129-97dc-4a72-9338-cc8927ff9e1a` 返回 6 文件、
5 项发现，并报告用量上限警告；不把该警告掩盖成无条件全绿。
已处理其 medium 严格 fresh 判断和四项低级别建议：清除警告必须使用明确
服务端状态；保留徽章附加类；健康文案复用同一 PHP 私有投影；PHP 逐个验证
summary/badge/label 标记；JS fixture 保留未标记的真实失败节点并验证不变。
修正后的生产行为和五种浏览器状态重新通过，后续仅对这些修正差异补审。

临时 SVN `trunk` 和 `tags/0.4.1` 必须使用内容校验同步（如 `rsync -ac`），
再比较所有文件字节。可复现包规范化文件时间后，仅比较大小/时间的同步可能
遗漏同长度的 POT/PO 行号变化；最终上传前需再核对上述最新候选包哈希。

最终差异补审：commit `4e8b2db`，session
`0ad146bd-5c15-4d2a-b369-1dfa73dceb7d`，5 文件、2 项非阻断 low 发现，
完整返回且没有用量上限警告。两项已补齐并通过最终 release:verify：缺失响应
状态时保留原 dataset，PHP 验证健康标签属性的准确值及失败态不携带该属性。
另外增加 missing-state 中文浏览器案例，警告和 stale dataset 均保留。
本轮最终 JS 15/15、六种浏览器状态通过，所有 OCR 发现均已处置；不把有
已处理发现的报告写成“零发现”。状态投影不新增 Cloud 请求或公共契约。

## CI OCR 接入诊断（2026-10-13）

PR #269 已合并为 `a4f85893304dae99f22ab3293c479a1fb2f4bfcb`；完整检查
中其余 13 项通过，OCR advisory 三次失败，不能把合并状态写成所有检查通过。
本地 `ocr llm test` 在当前 `qwen3.8-max` 上通过并验证工具调用往返，
不代表 CI 的 `glm-5.3` 配置通过。

隔离临时分支的最小诊断不输出 URL、密钥或原始响应：

- Run `38341597311`：Action 默认参数返回 HTTP 400／1210。
- Run `38341841316`：仅附带 `thinking.type=disabled`、无工具定义，仍返回
  HTTP 400／1210；固定诊断词确认模型不支持关闭思考。
- Run `38341710206`：去除额外参数后返回 HTTP 429／1113，余额或额度不足。

CI 修复显式传入 `llm_extra_body: '{}'`，不覆盖模型、URL、密钥或协议。
诊断 workflow 仅存在临时分支，不能合入发布分支。额度恢复后先复测连接，
再检查真实模型审查及完整 check rollup；不要用零文件跳过的绿色 job 代替。
0.4.1 包哈希保持 `5ac5bc5287d6eb6eb2bc4d18ed1998637287c2df7e6e1f92e79f5347351594a7`；
SVN 尚未提交，发布仍需单独确认。跨仓矩阵仍等待干净窗口。
