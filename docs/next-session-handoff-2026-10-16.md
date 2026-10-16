# 会话历史归纳与下一阶段交接 — 2026-10-16

## 记录范围

本记录依据引用会话“检查项目并列出后续建议”的历史消息、该会话中保存的验证结果，以及当前 Addon 仓库的已合并文档。它用于回答“已经做到哪一步”和“下一步从哪里继续”，不替代各项具体 closeout、契约或发布标准。

当前 Addon 基线为干净的 `master@b432b2b`。本记录只描述状态和后续顺序，不修改运行时契约、凭据、Cloud 数据或 WordPress.org 发布状态。

## 当前落点

- **Addon 代码状态**：0.4.1 的刷新状态、失败反馈和 Site Knowledge 投递恢复相关改动已经合并；依赖 PR #261（PHPStan）和 #262（WordPress stubs）也已合并。PR #273、#274 的文档与验证收口完成，当前工作树干净。
- **本地 Site Knowledge 投递**：现有有界恢复已完成 10/10 批；当前公开内容分母为 1,997（另有 1 条密码保护内容被排除），Cloud 接收/索引结果为 1,997/1,997，3 条近期/中段/较旧标题检索均通过。Addon 本地缓冲为 0、丢弃为 0、维护游标为 `idle`、最近一次投递成功。
- **Cloud 状态仍有待确认项**：Cloud 还保留 64 条当前 WordPress 已不存在的历史 post/page 记录；没有执行清理。`retrieval_acceptance` 在当前投影中缺失时由 Addon 显示为 `pending`，这不能证明 Cloud 正在运行自动验收。维护目标投影与实际使用的 Ollama embedding 之间也需要 Cloud owner 澄清；不应因此在 Addon 内发起重建。
- **配额事实**：最初的 Free 账号级 `vector_documents=100` 曾阻塞恢复；经运营处理后，M4 开发环境的共享 `free_v1` 计划显示为 99,999。该值属于共享开发计划，不应直接当作生产配额或账号专属覆盖。
- **0.4.1 发布状态**：候选包已完成发布验证并提交到 WordPress.org SVN revision `r3749721`；API、官方下载 ZIP、英文/中文目录页、0.4.1 changelog 和官方 0.4.1 zh_CN language pack 均已核对。官方语言包已通过 WP-CLI 安装到本地 `magick-ai.local`，locale、插件版本、语言文件哈希和固定中文字符串均验证通过。
- **OCR 发布门禁**：已确定并实现“只有显式 `composer pr:publish` 才自动触发本地 OCR”的方向，配套了共享计数、冷却、并发锁、HEAD 绑定、结果复用和失败阻断等离线验证。真实完整 OCR 曾因预算、超时、网络 EOF 和 prompt 上限问题未交付；对应候选没有获得完整真实审查，因此不能写成已通过。GitHub OCR workflow 继续保持手动停用。
- **跨仓状态**：2026-10-16T07:49:15Z 在三仓主分支和稳定默认工作区重跑七仓矩阵，7/7 gates 全部通过，M4 runtime acceptance 为 `not_requested`。矩阵结果已复查；它不替代 Cloud owner 对历史记录和状态投影的决策。

## 本轮收口判断

Addon 0.4.1 的代码、确定性门禁、候选包、SVN 发布、API、官方下载包、目录页和官方 zh_CN language pack 验证已经完成。发布后 language-pack 传播项已闭环。

本轮已提交并推送 Addon/Toolbox 的既有文档；Addon、Toolbox 和 Cloud 的辅助 worktree 已移除。含未提交实验内容的 worktree 在移除前分别保存为带说明的 Git stash，未丢失内容。三仓主工作树当前均无未提交文件。

整个历史项目仍有独立的未闭环事项：PR #260 仍开放，所有 required checks 通过，advisory code-review 因 OpenCodeReview 连续 HTTP 429 限流失败且没有产生代码评论；Toolbox PR #269 已合并，但 PR #265 仍开放且其 advisory code-review 失败。Cloud 的 64 条历史记录清理和状态投影语义仍由 Cloud owner 决定。这些事项不需要继续修改 Addon 运行时代码。

## 历史主线

### 1. 0.4.1 补丁候选形成

最初目标是基于 `master@aacce25` 完成低风险 0.4.1 候选：修复过期 entitlement 首屏误报、保留 60 秒一次性设置反馈、同步版本与翻译元数据，并把评论、视觉、图片 Provider 验收及较大 UX 保持延期。Addon 边界、Cloud endpoint allowlist、持久化字段和 WordPress 写入边界均保持不变。

该候选通过确定性测试、PHPStan、WordPress.org 检查、JS/i18n、包构建与严格 Plugin Check，并在 PR #267 合并为 `9ae7e6a`。随后通过浏览器补齐了中文后台、过期缓存提示、Site Knowledge 和连接管理页面的真实证据。

### 2. OCR 配置与发布门禁改造

会话先确认本地 OCR 与 GitHub OCR 使用的是不同的模型/endpoint 配置，CI 的 `glm-5.3` 曾出现参数不兼容和 429 限流；本地则使用 `qwen3.8-max`。因此确定采用本机统一配置，普通提交和定时任务不触发，只有显式 `composer pr:publish` 才运行一次本地审查；GitHub workflow 改为手动停用。

之后在 Workflow Toolbox 与 Addon 隔离工作树中实现共享限流和结果校验。离线行为测试、两仓确定性门禁和发布器 dry-run 均通过，但真实审查经历预算不足、Composer 外层超时、模型请求 EOF、上下文取消和 prompt 上限不足等失败。为避免把部分结果写成通过，两个 OCR 候选一直没有获得完整真实审查证据，也没有强行创建发布 PR。

### 3. 刷新反馈与 0.4.1 发布收口

刷新状态和失败反馈修复分别通过 PR #269、#271；随后生成 0.4.1 候选包并完成发布记录，PR #272 合并。发布门禁、包哈希、中文资源和本地验证均记录在 `docs/041-final-release-record-2026-10-14.md` 等文档中。WordPress.org 上传始终被保留为单独确认点。

### 4. Cloud 连接恢复与 Site Knowledge 恢复

本地 Cloud 隧道恢复后，Addon 先只读确认连接、缓冲和既有恢复游标，再继续已有的有界全量投递，没有新建第二个操作。首轮发现 Free 账号级向量配额为 100，导致本地投递完成不等于 Cloud 索引完成；会话明确区分了这两个事实。

用户选择先提高账号级额度后继续。运营侧处理后，10/10 批恢复完成，1,997 条当前公开内容全部被 Cloud 接收/索引，3 条有界标题检索通过。这个过程没有执行 rebuild、delete、Provider 调用或 WordPress 写入。

### 5. Cloud-owned 差异与历史记录修正

恢复后的只读审计发现 Cloud 比当前 WordPress 公共清单多 64 条历史 post/page 记录，均已不在当前本地数据库中。会话没有按数量删除它们，而是要求 Cloud owner 先核对站点身份、来源键和受支持的清理流程。

同一审计确认当前存量分块使用同一个 Ollama embedding，但部署投影仍显示固定生产 SiliconFlow 目标；同时 `retrieval_acceptance` 字段在当前 Cloud 状态中缺失，Addon 只能按契约回退为 `pending`。这些都是 Cloud 状态/契约问题，不应通过新增 Addon 逻辑掩盖。

### 6. 依赖与文档最终收口

PHPStan 与 WordPress stubs 的依赖 PR #261、#262 在修正 PR body contract 后均完成 13/13 检查并合并。PR #273 记录 Site Knowledge 配额恢复与发布复查，PR #274 记录依赖和中文后台复查；当前 Addon `master` 已同步且干净。历史文档中的过期登录、旧包哈希和未合并描述已由后续 closeout 解释或覆盖，但它们仍保留作历史证据。

## 下一阶段建议

按依赖关系排序，建议下一阶段分成四条线，先完成有外部决策的事项，再做跨仓收口：

1. **先定 Cloud 配额与历史记录策略（Cloud owner/运营）**
   - 确认开发环境把共享 `free_v1` 提高到 99,999 是否为临时测试设置。
   - 为生产环境确定账号级或计划级的正式配额，不把开发值复制到生产。
   - 对 64 条历史记录做站点身份、来源键和清理范围核对；在 Cloud 侧形成可审查的清理/保留决定前，Addon 只保持只读观察。
   - 明确 `retrieval_acceptance` 缺失字段的语义，以及 Ollama 实际 embedding 与 maintenance target projection 的契约对齐方式。

2. **完成 0.4.1 发布后的 language pack 收口**
   - 等 translation API 返回 0.4.1 且官方下载地址变为 HTTP 200。
   - 下载并校验官方 ZIP，通过 WP-CLI 安装到 `magick-ai.local`，验证 `zh_CN`、插件版本和固定中文字符串。
   - heartbeat 已负责低频监测；状态不变时保持安静，生成并验证后自动停止。

3. **然后收口本地 OCR 门禁（仅在确有发布需求时）**
   - 先把 Toolbox 共享门禁和 Addon 适配拆成可独立审查的小 PR，避免再次超过默认规模。
   - 在新的预算窗口只执行每仓一次完整本地 OCR，必须得到完整覆盖结果；失败、部分结果或超时都继续阻断发布并保留证据。
   - 继续沿用显式 `composer pr:publish` 触发、共享每日限额、冷却和并发锁；不要恢复普通 PR 自动调用的 GitHub OCR。

4. **最后重跑跨仓矩阵并做交接更新**
   - 等 Toolbox/Cloud 相关候选、依赖 PR 和 OCR 门禁进入稳定干净窗口后，从 `/Users/muze/gitee/npcink-workflow-toolbox` 执行 `composer quality:matrix:run`。
   - 记录七仓分支、SHA、工作树状态、Required/Advisory 检查和 M4 是否请求；不要把只读状态快照当成完整矩阵。
   - 根据 Cloud 配额、发布和 OCR 的实际结果，更新本交接文档和相关 closeout，删除已经失效的“当前待办”措辞，但保留历史段落。

在上述事项推进期间，Addon 侧只需要继续观察本地 Site Knowledge 自动投递：出现真实失败时按既有恢复入口和证据标准处理；正常状态下不要手动 flush、rebuild、delete 或重复 Provider 验收。

## 明确不纳入本阶段

- 不在 Addon 内实现 Cloud 的索引生命周期、配额真相、检索验收调度或历史索引清理。
- 不恢复 GitHub 自动 OCR，不把未完成的模型审查写成通过。
- 不把本地“投递完成”改写成 Cloud“索引完成”，也不把手工 3/3 标题检索改写成自动验收。
- 不在没有独立发布确认的情况下上传 WordPress.org SVN。
- 评论、视觉、图片生成、图片续传/部分成功和更大范围 UX 继续按各自仓库和预算另行排期。

## 可核对的依据

- `docs/site-knowledge-quota-recovery-closeout-2026-10-15.md`
- `docs/041-final-release-record-2026-10-14.md`
- `docs/wordpress-org-release-and-translation-log.md`
- `docs/verification-evidence-standard.md`
- `docs/cloud-addon-boundary.md`
- 引用会话：`01a12e1d-929f-7513-8ac1-2078816531b6`（本记录整理前已读取其历史消息）

## 回滚

本次只新增交接文档并更新文档索引，没有数据迁移、代码变更或外部写入。若文档需要修订，直接回滚本次文档提交即可，不影响运行时和 Cloud 数据。
