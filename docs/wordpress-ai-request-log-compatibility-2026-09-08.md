# WordPress AI Request Log Compatibility

Date: 2026-09-08. Status: historical evidence, updated 2026-09-09.

The log fix was merged in Addon #142. Normal batch recovery subsequently
passed (14 sent, 11 stored, 3 duplicates); see the recovery section below.
Natural Cron delivery remains unverified. Earlier "not merged" and "blocked"
statements describe the original checkpoints, not the latest state. The
consolidated acceptance record is
[WordPress AI acceptance and release handoff](wordpress-ai-acceptance-and-release-handoff-2026-09-08.md).

## Cause and Decision

The installed WordPress AI plugin is 1.3.0. Its request log manager accepts
`ai_client`, `mcp_tool`, and `ability`. The Addon bridge incorrectly supplied
content modalities (`text`, `image`, `vision`) as log types. The manager
returned false before repository insertion, while generation could succeed.
The previous test double accepted every type and therefore missed this defect.

All model connector bridge records now use `ai_client`. Modality remains in
`context.modality`; task, Cloud run ID, provider/model evidence, feature flags,
and metadata-only payload rules remain unchanged. No official-plugin patch,
Cloud change, settings change, article write, or new logging system is needed.
Logging remains optional and must not turn an otherwise successful model
request into a generation failure.

## Verification

- Updated the test double to reject unsupported types, matching AI 1.3.0.
  The focused regression failed before the fix and passed afterward.
- Covered text/image success, vision, text failure, feature disablement, and
  exclusion of input/output content from logs.
- The actual installed official log manager was exercised in an isolated PHP
  process with an in-memory repository substitute: old modalities returned
  false; `ai_client` reached repository insertion. No WordPress bootstrap,
  database operation, or model call occurred in that probe.
- `composer run test:all` passed (PHP syntax, contracts/behavior, boundary).
- `git diff --check` passed.
- Playground smoke is not applicable: no bootstrap, activation, public PHP
  signature, default connector state, or PHP/WordPress baseline changed.
- No cross-repository milestone is claimed; the central matrix was not run.

The Local site Addon path is a symlink to this repository, so the next PHP
request uses this source. Existing unrelated working-tree edits were preserved.
Rollback must reverse only the log type/context hunk and this task's test/doc
changes, not reset the shared connector file.

## Remaining Acceptance

This does not backfill old logs or prove live database persistence. The next
authorized real test-draft request must show the official request-log entry,
correlate its Cloud run with actual model/context evidence, then exercise
review and save. The existing suggestion belongs to article 280982; copying
it to another draft cannot prove adoption of that original run. Modified text
also must not be counted as confirmed adoption solely from an unmatched hash.

## Live Acceptance Follow-up

On 2026-09-08, created dedicated draft 281071 from public article 280982.
The draft was not published. Through the official AI title button in Edge,
performed one generation, inserted the unchanged candidate, and saved draft.
Candidate: `Npcink AI 插件系列上线 WordPress 官方插件库`.

- Cloud run: `run_21969d96e26041bb8ac5713ca6846c09`.
- Official log: `18320642-b742-4360-963e-6dd96f1d2280`, `ai_client`, success,
  7302 ms, `openai / gpt-5.5`, and matching `context.cloud_run_id`.
  The log UI changed from zero requests to one successful request.
- Cloud signed run/result reads confirmed success, no fallback, and two
  provider records: one `ollama-m4-embedding / qwen3-embedding:0.6b` call and
  one `openai / gpt-5.5` call, both retry_count zero. This was one generation
  action, not one total provider call. No M4 host operation was performed.
- Local save observation: `saved_exact_output`, confidence high, and matching
  run ID. Journey events included generation succeeded, accepted, save succeeded.
- Quality upload returned success with 2 sent and 2 stored; matching local
  quality events were removed by the existing successful upload workflow.
- Ordinary journey batch upload failed: `run_id must reference a run owned by
  the authenticated site`. The 24-item buffer included historical
  `run_browser_fake_1ec7df2e15e6_*` IDs; the browser smoke script creates this
  fake-ID format. No historical records were deleted or rewritten.
- A bounded diagnostic send of only this real run's 3 journey events through
  the existing signed client returned accepted_count=3, stored_count=3,
  duplicate_count=0. They remain in the local buffer because this diagnostic
  did not mutate normal buffer bookkeeping. Normal batch recovery remains open.
- Original article 280982 retains title `AI 插件系列` and modification time
  `2026-06-12 09:16:05` UTC. Draft body equals original body byte-for-byte.

The happy path through Cloud receipt is demonstrated, but automatic batch
delivery is NOT accepted. Vector execution does not prove retrieved content
was injected into the generation prompt: signed run/result responses did not
expose an explicit site-context-applied receipt. This remains unverified.

## 工作审视报告

### 后续恢复结果（2026-09-08）

经用户确认后，先核实 siteurl 为 `http://magick-ai.local`，完整备份当时
24 条用户旅程记录至非自动加载的本地数据库 option：
`npcink_acceptance_journey_backup_20260908_281071`。
该 option 包含原始 `buffer` 和精确隔离的 `quarantined`，创建后逐项回读验证。
随后仅从活动缓冲区隔离以下 4 个假运行 ID 的 10 条事件：

- `run_browser_fake_1ec7df2e15e6_2`
- `run_browser_fake_1ec7df2e15e6_3`
- `run_browser_fake_1ec7df2e15e6_4`
- `run_browser_fake_1ec7df2e15e6_5`

调用原有 `Npcink_Cloud_Customer_Journey::flush_buffer()` 一次后，回执为
`ok=true, sent_count=14, stored_count=11, duplicate_count=3, buffer_count=0`。
因此此前“正常批次上传受阻”已恢复，定向发送产生的 3 条重复被正确识别。
这证明现有上传流程恢复，不等于未来 WP-Cron 的定时调度已经观察通过。
未删除备份、未清空真实事件、未修改插件代码或新增模型调用。

恢复时不要直接把完整旧 buffer 覆盖当前缓冲区，否则会重放已上传数据。
如需恢复隔离记录，应先检查最新缓冲区，再按 event_id 去重合并备份中的
quarantined；假运行事件不能重新投入 Cloud 上传队列。

背景注入核对仍需管理员登录：实际打开
`http://127.0.0.1:18010/admin/login`，显示管理员密钥登录表单。
Cloud 源码已有 `generation_context_status/reason/reference_count/chars`
元数据，但尚未读取本次运行的对应值，不能以源码或向量调用代替实际证据。
浏览器模拟测试的旅程隔离缺陷仍应单独修复，避免再次污染真实站点缓冲区。

### 原定目标

真实验证日志修复以及测试草稿从生成、采用、保存到 Cloud 元数据接收的路径。

### 完成情况

- [x] 官方日志真实入库、草稿保存、同一运行关联和定向 Cloud 接收。
- [ ] 正常用户旅程整批上传：被历史测试运行记录阻塞。
- [ ] 站点背景实际注入：站点侧运行接口证据不足。

### 发现的问题

| 严重程度 | 具体问题 | 根本原因 | 改进建议 |
| --- | --- | --- | --- |
| 必须改正 | 用户旅程缓冲区含 `run_browser_fake_*`，正常批次被拒绝 | 浏览器模拟测试与真实站点遥测隔离不足 | 单独修复测试隔离；经确认备份、隔离精确的无效测试记录，再验证正常上传 |
| 应当改正 | 运行结果未提供背景已应用的明确证据 | 验收证据未覆盖“检索参与”和“实际注入”的区别 | 优先读取已有 Cloud 详细证据，不仅凭 embedding 调用判定 |

### 做得好的地方

- 使用独立草稿，原文不变；未把手动定向发送包装为正常自动上传成功。
- 限制为一次生成，明确披露其内部包含两次 Provider 调用。

### 下次重点关注

先处理历史测试数据隔离与正常批次恢复，再补背景注入证据；不追加无目的模型调用。
