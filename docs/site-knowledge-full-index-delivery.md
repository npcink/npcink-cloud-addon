# Site Knowledge Full-Index Delivery

`npcink-cloud-addon` delivers an administrator-requested `start` or `rebuild`
across the site's bounded public `post`, `page` and eligible image attachment
corpus. PDF/video and image download/OCR remain outside this delivery path.

The Addon remains a transport bridge:

- WordPress is the source-content owner and selects at most 10,000 published
  typed post/page/media identities for one explicit administrator action.
- The Addon plans batches against 600,000 escaped JSON bytes and at most 200 public manifests through the existing
  `POST /v1/runtime/execute` contract.
- The administrator request only records one bounded local delivery cursor and
  schedules the existing Site Knowledge flush hook. Each Cron invocation sends
  or polls at most one Cloud batch, so a large corpus does not hold the
  administrator HTTP request open across many Cloud calls. Long bodies increase the batch count.
- A rebuild's first batch uses `sync_mode=rebuild` with empty `post_ids`, so
  Cloud clears the site's previous index before indexing that batch. Remaining
  batches use `sync_mode=refresh`.
- Each batch has a stable idempotency key. The full cursor records selected
  typed public IDs, current batch, bounded attempts, an optional Cloud run ID,
  and a bounded poll generation. The same maintenance option also retains
  immutable in-flight delivery payloads and scoped administrator intents. Once an exact run ID is persisted, the body snapshot is released while its key, fingerprint and run identity remain for writer fencing; lost responses retain exact payloads for replay. One Cloud run may be polled at most 120 times before
  consuming a delivery attempt; three exhausted attempts block the cursor.
  This does not create a local index job, queue, scheduler truth, workflow
  runtime, or vector truth.
- A second start, rebuild, delete, or automatic maintenance request cannot
  overwrite an active full-index cursor. Ordinary content changes remain in the
  existing bounded change buffer and are scheduled again after the full-index
  delivery completes.
- Cursor advancement and deletion are conditional on the exact version read by
  the Cron callback, preventing an older overlapping callback from mutating a
  newer administrator request.
- Post/page bodies use `wordpress_public_text_bytes.v2`, capped at 48,000 UTF-8 bytes after sanitization. Comments and image descriptions retain the 1,800-byte limit. Delivery evidence records exact sanitized source/delivered byte and character counts and explicit truncation. Incidental embedded
  URLs, email addresses, and phone-like sequences are removed before delivery;
  the canonical public permalink remains a separate allowlisted field.

Cloud continues to own queued execution, index lifecycle, vector storage,
embedding execution, freshness, and diagnostics. Every request remains
`suggestion_only` and cannot write WordPress content.

## Refresh replacement and explicit withdrawal (2026-10-28 candidate)

Ordinary refresh sends prepared public sources for replacement. An omitted
manifest is not deletion evidence. The paired Cloud safety candidate preserves
old rows when collection, quota, embedding or processing limits prevent a
replacement. Explicit rebuild remains destructive; it is not an atomic backup.

On refresh the bridge re-reads each selected post/page. Missing, private,
password-protected or otherwise non-public sources produce bounded
`withdrawn_post_ids`; public sources missing from the collected batch fail
before dispatch. These markers use the existing signed sync transport, not
WordPress write authorization. The legacy `post_ids` selector is retained for
older Cloud installations. A post withdrawal retains the existing legacy scope
(post/page root and associated comments); media uses its separate typed lane.

Comment hooks retain at most 200 comment identities and SHA-256 content/state
revisions beside parent IDs in the existing change-buffer option. No comment
plaintext, author or email is stored in that buffer. Before dispatch the bridge
re-reads each comment: approved public content is sanitized and capped at 1,800
UTF-8 bytes; missing/unapproved comments produce exact `withdrawn_comment_ids`.
A parent mismatch fails collection. The revision participates in the existing
in-flight fingerprint, so a new comment change cannot be acknowledged by an
older payload. A normal batch contains at most 25 parents and 200 comments.
Overflow uses the existing `dropped_count` attention diagnostic; this bounded
bridge does not guarantee lossless capture under overflow or retry exhaustion.
Comment and post withdrawals are excluded from a split pure-media payload.

Migration order for this specific semantic change is **Addon first, then Cloud**.
Install the explicit-withdrawal producer before enabling preserve-on-omission
Cloud semantics. Other signed producers must also migrate legitimate withdrawals
to explicit delete/withdrawal markers; older producers can still update public
content, but omission no longer withdraws it. These are candidate source
contracts until merged/runtime evidence is recorded, not a production receipt.
No new option, queue, scheduler, ability or WordPress content write is introduced.

## Automatic reconciliation cursor

The hourly change scan enumerates at most 50 published candidates in ascending
`(post_modified_gmt, ID)` order. A missing cursor starts at the oldest
candidate; a recent successful delivery is not evidence that earlier content
was covered. Existing cursors remain authoritative and are never reset by
initialization. An empty scan records its execution without inventing a
watermark from the delivery clock.

Reconciliation uses only spare capacity in the existing 500-ID buffer. It
stops before the first new ID that cannot fit, preserves pending live changes
and retry attempts, and advances only through retained candidates. This scan
does not establish Cloud coverage: actual document/chunk evidence is required.
It does not recover arbitrary holes before an existing cursor or replace the
separately authorized article/full-content refresh paths. Initial catch-up may
re-send already covered content and require many natural hourly invocations.


## 有界内容选择、排除与恢复

站长从现有知识库状态卡的折叠「选择知识库内容」提交 1–100 项公开内容；
只有 `manage_options`、原 admin-post nonce、当前 credential/site scope 和准确
`EXCLUDE` / `RESTORE` 确认词均匹配才记录操作。原 handler 负责权限与 nonce，
Admin Actions 严格解析选择集。数量先于去重检查，不截断，不把空列表扩成全站删除。
记录请求不发 Cloud HTTP，页面渲染也不发 Cloud HTTP。健康态隐藏 Sync；失败恢复仍
沿原入口。选择器当前只显示前 100 个本地候选，不能据此声称支持完整媒体库管理。

持久身份为 `(sha256(base_url|site_id|key_id), source_type, source_id)`：
`post`、`page` 保留真实 WP 类型，`media` 对应图片 attachment。`text` 仅是容量桶，
不是 source 类型；评论按父 post/page 关联恢复，不提供独立评论写入选择器。
切换凭据不会移动或删除旧命名空间。断开连接和 cleanup 保留未决的既有 maintenance
option；其中没有复制 Cloud secret。WordPress 正文、附件文件及 metadata 均不修改。

状态与条件：

| 本地意图状态 | 自动 producer | 解除条件 |
| --- | --- | --- |
| `pending_delete` | 收集、buffer、full/reconcile 及最终派发均抑制 | D0/所需 C1 成功，旧 writer 全部获正面终态证据，fresh typed root/关联评论的 document 和 stored chunks 全 0 |
| `excluded` | 持续抑制 | 管理员对同一 typed 集合明确请求恢复 |
| `restore_pending` | 仅该批准的 restore attempt 可绕过，其余 producer 继续抑制 | 实际 refresh run 成功、完整结果无 failed/skipped/quota 项、每个批准输入 accepted/indexed 或 unchanged、新鲜 public document/chunks 成立，并满足已批准评论关联数 |
| blocked 操作 | 对应 source 保持 pending 抑制 | 不以超时、空 buffer 或 aggregate usage 自动解除 |

所有 mutation producer（普通 buffer、article refresh、Toolbox/public facade、
full/reconcile 和图片事件）共用原 `MAINTENANCE_OPTION` 的 serialized SQL CAS owner。
收集时过滤不能替代最终派发检查。派发前保存不可变 payload、fingerprint、key 和 owner；
并发请求能登记 pending 并保留这次旧 writer，不能抹去或接管 owner。确定尚未发送的
owner 可标 `not_sent`；HTTP timeout、响应解析失败、任意 4xx 都不能据此取消 pending。
发送后保存失败或进程消失的 owner 保守阻断，不借 lease 自动断言无旧 writer。

### D0、C1 与 Cloud writer 证据

同批准 operation O、规范 typed 集合 S 首次删除使用稳定 D0。若批准时存在已派发
producer，则等待其所有真实 writer 终态，再按排序 `(run_id, terminal_status)` 集合 T
派发至多一个 distinct C1。D0/C1/restore 的 key 独立，C1 失去响应只重放原 key/payload，
不会用 D0 的已缓存成功，也不生成 C2。已知 run 优先读取；未知 run ID 保留原 payload
并以原 key 恢复身份。每轮最多核对 4 个旧 writer，正面证据进入同一 CAS 进度；超过
120 轮只标 blocked，run ID、原请求与抑制均保留。

writer 证据逐项匹配当前 site、run、ability、contract、idempotency key，且必须具有
`execution_kind=knowledge`、`provider_id=site_knowledge`、
`model_id=site-knowledge-managed`、`instance_id=cloud-runtime` 和有效 finished_at。
成功必须没有 error_code。失败只接受冻结 Cloud 821/66 中正常 executor unwind 来源：
`source_items_invalid`、`source_identity_conflict`、`source_selector_conflict`、
`zilliz_probe_failed`、`zilliz_delete_failed`、`zilliz_upsert_failed`、
`embedding_provider_missing`、`embedding_budget_context_missing`、`embedding_invalid`、
`embedding_dimension_mismatch`（均为 `site_knowledge.` 前缀）。失败可能已部分写入，
仍需 C1。operator stale failure、auto-fail、cancel、其它未知失败不构成 writer fence。
这不是对任意 succeeded/failed 的泛化，也不依赖 fixture 进程 join 作为产品条件。

升级前已配置、却没有可追溯派发记录的旧安装保持 `legacy_writer_identity_unknown`；
旧 flat full cursor 同样保留。未配置且空状态时建立 coverage 起点用于之后完整登记
本安装的派发；它不会追溯证明旧 Cloud worker 已结束。没有自动清除此未知标记的 UI，
也没有新增 Cloud run 枚举接口。本地 fixture 能演练可追溯的新链路，不能把旧安装
blocked 写成完成。

### 图片、full 与恢复结果

图片来自真实 attachment：允许 JPEG/PNG/WebP/GIF/AVIF，attachment 非 trash/private，
实际 parent 是公开无密码的 post/page。`inherit` 和文章里出现 URL 不能单独证明公开；
无 parent 的图片本批保守不选。发送真实 url/title/alt/caption/description/modified_gmt
及稳定 metadata fingerprint；无可索引文本则拒绝，不造 visual_summary、不抓图、不 OCR。

full 使用同一 typed cursor，总上限 10,000、每批最多 200；post/page 批次之后发送图片
批次，首次 rebuild 清理后仍会交付合格图片。图片不进入 wire post_ids，恢复图片发送
pure media_items refresh，避免清理父文章。普通混合 buffer 也先拆纯图片，再投递文字。
post/page 恢复读取 Cloud comments_enabled，按当前公开批准评论的真实父关联生成内容；
不发送作者/email。公开性变化、评论配置变化、配额/失败/结果丢失均保持 restore_pending。
fresh presence 只证明 relational_index；必须独立以真实 backend 执行测试证明 vector，
不能声称 SQL+vector 跨库原子事务。usage 始终只展示容量，不决定 source 是否消失。

### 运行寿命与内存预算

仍是一个既有 option/CAS，没有新队列、锁、表或框架。每次 JSON payload 上限 900,000
字节，整个 serialized maintenance envelope 上限 4,194,304 字节；包含 full cursor、
8 个以内 credential scopes、每 scope 最多 500 个 source/dispatch 和 100 个未决意图。
达到 400 条派发或 2 MiB 压力后，每次最多核对 8 个 producer：只有 Cloud 正面 writer
终态或确证 not_sent 且没有 active/blocked 操作引用、没有当前 full 请求引用才退休。
unknown/inflight 不按时间清除；总预算满时拒绝新发、保留已有证据。

操作完成后立即删除其正文 payload 快照，保留 key/fingerprint/run 最小证据和 tombstone；
下一次管理员请求在同一 CAS 中退休已完成意图及其 attempt。退休本身不改变 producer
确定性键，同 payload 再发仍由 Cloud replay，不能由退休制造新执行。只有明确完成
restore 才更新 producer_generation，防止后来自动更新复用删除前的旧成功结果。

本地确定性回归覆盖 521 次真实 fixture producer 调用、105 轮排除/恢复、未知 writer
与 active 引用保留、相同 retired key replay 和 700 KB payload 的总字节压力。其 SQL CAS
与 Cloud HTTP 均使用隔离 double；它们不是 MySQL、PG/Redis/vector 或真实浏览器证据。
24 项跨系统验收仍必须由 parent 对冻结候选执行，未执行项统一记 NOT_RUN。

新 full cursor 的 claim 与 pending/restore_pending 检查属于同一个 raw serialized CAS；
存在未决 source 时不先拍一个遗漏该 source 的新 full 快照。反向也一样：已有未决 full
cursor 时暂不接受 restore，先完成原 full delivery，避免稍后的全站 clear 抹掉已恢复项。

## Full-body projection rollout (2026-10-30 local candidate)

Deploy the Cloud reader for `wordpress_public_text_bytes.v2` before enabling
this Addon producer. The v1 excerpt reader remains compatible. The field name
`content_excerpt` is retained on the wire; v2 carries a bounded body, not an
assertion that every WordPress article fits. No index or model migration is
triggered by installing this code. Existing sources need ordinary refresh.

Full-index planning stores typed IDs, not a second content snapshot. Dispatch
re-reads public state and enforces the final 900,000-byte limit. If many articles
grow after planning, or comments/envelope exceed the reserved space, delivery
fails visibly with the existing bounded retry/blocked state; it does not send
an oversized request or acknowledge omitted sources. A new explicit full
operation can plan a fresh manifest after the old operation is resolved.
Large restore selections also retain the existing payload/durability guard.
Rebuild is still explicitly destructive; prefer refresh for safe replacement.

Local behavior covers 605 Chinese long bodies through byte-sized batches,
late tail facts, UTF-8 boundaries, explicit partial delivery, accepted-run body
compaction, and exact lost-response replay retention. This is local evidence,
not CI, M4, production or customer relevance acceptance.
