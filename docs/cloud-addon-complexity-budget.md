# Cloud Addon Complexity Budget

Status: active for `npcink-cloud-addon`.

## Purpose

This addon is intentionally allowed to carry security and boundary complexity,
but it must not grow product-control complexity.

The useful complexity here protects the local WordPress host from accidental
Cloud ownership drift. The addon remains a connector and transport layer; local
Core remains the control plane for proposal display, approval, record, replace,
rollback, and all WordPress writes.

## Complexity Worth Keeping

Keep these even if they make the code less minimal:

- HMAC signing, trace headers, idempotency headers, and endpoint allowlists.
- Authenticated encryption for addon-owned signing credentials at rest, with
  fail-closed handling for tampering, decryption failure, or salt rotation.
- Save-and-Verify gating before media derivative dispatch.
- Ability payload checks that reject credentials, Authorization data, signed
  headers, tokens, and Cloud signing fields.
- Bounded source and watermark media derivative multipart transport.
- Bounded WordPress AI alt-text attachment validation and one-purpose,
  short-TTL image upload transport followed by Artifact-id-only execution.
- Image watermark/logo fail-closed behavior unless the local ability supplies a
  watermark plan and the host supplies one short TTL artifact or upload; text
  watermark plans must remain structured options without a watermark source.
- Bounded signed derivative artifact preview download through the explicit
  runtime artifact download endpoint.
- Non-expired Cloud artifact id requirements for derivative proposal adoption.
- Cloud result to artifact binding checks for artifact id, run id, and checksum.
- Preview-only proposal payloads with `final_write_owner=local_wordpress_host`.
- Tests that prove the addon does not write WordPress objects or attachment
  metadata.

These checks are defensive boundary rules, not product features.

## Complexity To Stop

Do not add these to this addon:

- Proposal UI, approval UI, record, replace, rollback, or preflight ownership.
- Attachment main-file replacement or `_wp_attachment_metadata` updates.
- Artifact registry, generic upload/download manager, or source/logo registry.
- Watermark/logo planning, branding defaults, or logo storage.
- Workflow/task queue control, scheduler truth, repair console, or operator
  recovery console.
- Billing truth, invoice/payment controls, or Cloud service operations console.
- Router, prompt, preset, model-center, ability, workflow, MCP, or OpenClaw
  control planes.
- Generic public Cloud proxy methods or low-level request exposure.

If a change needs any item above, it belongs in local Core, Adapter, or Cloud
service-plane code, not in this addon.

## Review Question

Before adding code, ask:

Is this transport/detail, or is it control/write truth?

- Transport/detail can stay here when bounded and endpoint-allowlisted.
- Control/write truth must stay with the local WordPress host or the appropriate
  Cloud service-plane owner.

## Complexity Size Ratchet

`tests/static-contracts.php` enforces a line-count ratchet on the four widest
connector classes so they cannot grow unnoticed:

- `includes/class-cloud-runtime-client.php` — 1727 lines (was 4314 before the
  2026-10-06 payload-guard split, then 2324 before the same-day diagnostics
  split, then 1968 before the same-day media-payload split into
  `class-cloud-runtime-request-guards.php`,
  `class-cloud-runtime-diagnostics.php`, and
  `class-cloud-runtime-media-payloads.php`)
- `includes/class-cloud-runtime-request-guards.php` — 2020 lines (pure
  `normalize_*` request validators, forbidden-key walkers, PII scan, and
  bounded-text/sanitize helpers with zero instance state)
- `includes/class-cloud-runtime-diagnostics.php` — 387 lines (static readiness
  projection and secret redaction over probe arrays and the caller's
  configuration snapshot)
- `includes/class-cloud-runtime-media-payloads.php` — 280 lines (static
  multipart assembly and exact upload/delivery-ack response validation for
  the bounded media endpoints)
- `includes/class-cloud-settings-page.php` — 2840 lines (was 3280 before the
  2026-10-04 handler split into `class-cloud-settings-actions.php`; raised from
  2207 for the 2026-10-12 locale-aware privacy URL, no-JS disconnect confirmation,
  and removal of the retired Site Knowledge coverage renderer; raised again for
  the 0.4.1 stale-entitlement service-health projection and its bounded
  behavior coverage; raised by three lines for the private delivery-failure marker
  protecting real Site Knowledge warnings, including error-code-only failures,
  during entitlement refresh; one further line reuses the private fresh-health
  projection for the browser label instead of duplicating its copy; raised by
  five lines for routing dropped-content recovery to the existing Site Knowledge
  update action; raised by thirteen lines to project active dropped-content recovery as
  updating while retaining failures in diagnostics and restoring attention after delivery fails;
  raised by 23 lines for shared escaped AJAX status fragments, stale-evidence gating,
  and failure-only retry feedback)
- `includes/class-cloud-settings-actions.php` — 1230 lines (settings request
  handlers and their transient state; the render class stays a projection;
  raised from 1106 with reason: the 2026-10-11 first-connection verify-failure
  path keeps exchanged credentials in the unverified state instead of
  discarding them; raised again for the 2026-10-12 server-side no-JS disconnect
  confirmation guard; raised by one line to distinguish scheduled recovery notices from completed delivery; raised by two lines to return current admin status fragments with existing authenticated reads; raised from 1170 with reason: the 2026-10-20 local disconnect remembers Cloud Base URL so reconnect does not fall back to localhost:8010)
- `includes/class-cloud-wordpress-ai-connector.php` — 1130 lines (raised from 1090 for plain Cloud AI error families and the last-AI-error support code) (was 2767 before the
  2026-10-06 per-class split into seven satellite files; raised from 1002 with
  reason: the 2026-10-11 in-progress run probe shares one
  `response_indicates_in_progress_run()` helper across the three models; raised
  from 1024 with reason: the 2026-10-20 WordPress AI 1.4 request-log gate aligns
  with Features Loader so Cloud ability invokes write Tools → AI request logs;
  raised from 1035 with reason: the 2026-10-20 connector-runtime request-log
  bridge maps Cloud `result.usage` tokens_in/out into host tokens_input/output)
- `includes/class-cloud-wordpress-ai-alt-text-handoff.php` — 338 lines
- `includes/class-cloud-wordpress-ai-availability.php` — 32 lines
- `includes/class-cloud-wordpress-ai-image-model.php` — 496 lines (raised from
  492 for the 2026-10-12 precise expired-artifact classification before
  verified media delivery)
- `includes/class-cloud-wordpress-ai-model-metadata-directory.php` — 178 lines
- `includes/class-cloud-wordpress-ai-provider.php` — 79 lines
- `includes/class-cloud-wordpress-ai-text-model.php` — 390 lines
- `includes/class-cloud-wordpress-ai-vision-text-model.php` — 126 lines
- `includes/class-cloud-wordpress-ai-scene-model.php` — 159 lines (shared
  metadata/config plumbing, prompt/result projection helpers, and the
  single plain-text exception sink `throw_user_facing()` required by the
  strict Plugin Check gate)
- `includes/class-cloud-media-derivative-transport.php` — 715 lines (was 2542 before
  the 2026-10-06 source-validation and artifact-verification split into
  `class-cloud-media-source-validation.php` and
  `class-cloud-media-artifact-verification.php`; lowered from 714 with reason:
  the 2026-10-07 auto-safe profile literal was single-sourced into
  `Npcink_Cloud_Media_Governance_Validation::AUTO_SAFE_PROFILE`)
- `includes/class-cloud-media-artifact-verification.php` — 701 lines (descriptor
  verification, verified pull-and-ack transfer, and the shared caps/normalizers;
  raised from 699 with reason: the 2026-10-07 delivery-header check parses the
  artifact expiry with `strict_timestamp` like the rest of the class instead of
  a one-off loose `strtotime`, failing closed on unparseable expiry)
- `includes/class-cloud-media-source-validation.php` — 293 lines (local upload
  descriptor validation before job dispatch; raised from 279 with reason: the
  2026-10-07 explicit invalid-source rejection reports non-string direct byte
  sources as a descriptor error instead of a misleading empty-file error)
- `includes/class-cloud-media-governance-validation.php` — 282 lines (strict
  governance-canary result validation and bounded projection; raised from 281
  with reason: the public `AUTO_SAFE_PROFILE` constant single-sources the
  auto-safe policy version shared with the transport)
- `includes/class-cloud-media-plan-projection.php` — 709 lines (local proposal and
  optimization-plan projection with the request-contract validation)

The ratchet is a ceiling, not a target. Raise a limit only together with a
documented reason here; prefer paying it down instead. The settings-page
handler split has landed: `Npcink_Cloud_Settings_Actions` owns the twelve
admin-post/wp-ajax request handlers, the authorization flow machinery, and the
shared transient state (notices, permission feedback, readiness results,
consent prompt); the page class renders and reads that state through the
public accessor seam. Further paydown direction for the remaining render class
is collapsing duplicated disclosure markup behind shared partials, keeping
`docs/admin-surface-standard.md` tab and copy rules authoritative.

## PHPStan Baseline Classification (2026-10-06)

The advisory baseline has been reduced 60 -> 44. The remaining entries are
classified and future reduction must treat the classes differently:

1. Defensive re-validation family (the large majority): fail-closed
   re-checks where the idealized WordPress core stubs claim certainty that
   real runtime data does not guarantee (`getimagesize()` shapes, non-null
   `WP_Post`/`WP_Comment` properties, narrowed `is_array`/`is_string`
   checks, option-name constant guards, cron flush callbacks returning
   bounded delivery facts for tests and projections). Do NOT zero the
   baseline by deleting these checks — they are "complexity worth keeping";
   remove one only when the real upstream guarantee changes.
2. Contract fixtures (2): `tab_url()` and `page_form_action_url()` have no
   production callers and stay because behavior contracts reflect on them
   to verify URL shapes.

New findings follow the 2026-10-06 (PR #217) bar: fix real defects and
provably dead expressions (duplicate catalog keys, stale PHPDoc, redundant
`array_values`, conditions proven constant by narrowing); classify whatever
remains instead of silently growing the baseline. Prefer fixing a new
finding at the highest analysis level that reproduces it over baselining it
at level 5; raising the global level stays a deliberate, separately reviewed
step taken only as the defensive re-validation family shrinks.

## PHP And WordPress Baseline Review

The connector floor stays `PHP 8.0` (`Requires PHP: 8.0`, composer platform
`8.0.99`) until an annual review — each October, and again before any
WordPress.org release — shows that raising it serves the remaining installs:
the current floor's share of WordPress.org installations has fallen under 5%
and the proposed newer floor is itself in active security support. A floor
bump is a compatibility-baseline change and owes Tier B playground evidence
plus a CI matrix update in the same PR, per
`docs/verification-evidence-standard.md`.

Annual review record — 2026-10-07 (October trigger, ahead of the 0.3.0
release): WordPress.org reported PHP 8.0 at 3.96% of installs
(`api.wordpress.org/stats/php/1.1`), under the 5% trigger, but no raise
qualified this cycle. The only single-step candidate, 8.1, left security
support on 2025-12-31, and the next candidate, 8.2, exits it on
2026-12-31 — raising that far this quarter would strand the 8.0 and 8.1
cohorts together (15.2% of installs, about one in five installs the
current floor serves) to buy at most three months of a supported floor,
which does not serve the remaining installs. The floor therefore stays
`PHP 8.0` for `0.3.0`. Future reviews should apply the same stranding
check: sum the shares of every version at or above the current floor and
below the proposed one, and treat a raise that strands materially more
than the 5% trigger as failing the "serves the remaining installs" test
even when both literal conditions pass. The expected next raise is one
step to 8.3 (security support to 2027-12-31) once the 8.0 and 8.1 cohorts
shrink.

## Test Structure

Tests are split by purpose:

- `tests/static-contracts.php` checks source, docs, and boundary text for
  forbidden surfaces and required contracts.
- `tests/behavior-media-derivative.php` calls public PHP APIs with WordPress
  stubs to prove fail-closed behavior and proposal payload shape.
- WordPress AI connector behavior tests prove the alt-text path requires an
  authorized local attachment, validates its short-TTL Artifact, and never
  falls back to URLs, Data URLs, base64, or WordPress writes.
- `tests/playground/` and `scripts/smoke-playground.sh` prove that the current
  checkout activates in a fresh SQLite/WASM WordPress instance and preserves
  the default fail-closed connector state. They do not use Cloud credentials or
  prove MySQL, media, Portal, browser, or production behavior.
- `tests/helpers.php` contains shared assertions, file readers, WordPress stubs,
  HTTP stubs, settings seed helpers, and media derivative fixtures, and installs
  the fail-on-diagnostics guard that turns unexpected warnings, notices, and
  deprecations into loud suite failures in every test process.
- `tests/behavior-output-harness.php` proves that guard in throwaway
  subprocesses (unexpected diagnostic exits 1; `@`-suppressed diagnostics keep
  PHP semantics).
- `tests/run.php` is only the aggregate entry point used by Composer.

Keep new tests in the narrowest matching file. Do not add product workflow
simulation to the helper layer.

## Verification Tiers

Keep evidence levels separate:

- `composer run test:all` is the deterministic PHP/static contract baseline.
- `composer run smoke:playground` is the disposable activation and default
  connector-boundary compatibility gate. Run it for bootstrap, activation,
  public connector API, default credential/connector state, and WordPress/PHP
  baseline changes.
- Local MySQL/Cloud and browser smokes prove their own real integration paths;
  release and production checks remain separate evidence.

Playground is intentionally optional from the default Composer/CI path because
it downloads a pinned runtime. A PR in the triggering scope must include its
result or an explicit not-applicable reason; do not silently treat a passing
Playground run as a substitute for a higher tier.

## Deterministic Performance And Safety Baseline

The current pre-user baseline favors deterministic work-amplification guards
over wall-clock thresholds that vary with the WordPress host:

- plugin file load and `npcink_cloud_addon_bootstrap()` must issue zero outbound
  HTTP requests;
- repeated bootstrap schedule synchronization must not read the normally absent
  Site Knowledge cursor or change-buffer options; explicit permission changes
  own the low-frequency resume check;
- repeated schedule synchronization must retain exactly one hourly
  observability event and one hourly Site Knowledge reconciliation event;
- an unexpected recurring interval must be corrected once, without network
  traffic;
- observability remains capped at 200 buffered events and 50 events per
  request; Site Knowledge remains capped at 500 post IDs, 25 change IDs per
  request, and three delivery attempts;
- uncertain retries of an unchanged observability or Site Knowledge change
  payload must reuse its content-addressed idempotency key. An unchanged Site
  Knowledge document payload intentionally does not create duplicate Cloud
  indexing work; changed document content produces a different key.
- successful flushes must re-read the latest local buffer and remove only the
  exact event identities or Site Knowledge document fingerprints that Cloud
  accepted, preserving changes captured while HTTP was in flight.

These guards run under `composer run test:all`. Endpoint latency sampling stays
outside this connector because Toolbox owns the operator-facing request surface
and the Cloud service owns hosted runtime latency.

Do not add a distributed lock or a new async scheduler to satisfy hypothetical
traffic. Revisit cross-request locking only after overlapping Cron execution is
observed in profiling. Administrator full-index delivery reuses the existing
bounded cursor and flush hook because the prior synchronous path amplified one
admin request into as many as 50 sequential Cloud calls. The cursor remains
delivery durability only and must not grow into workflow or scheduler truth.
The existing cursor option is claimed atomically for a new request, and later
cursor transitions use exact-version conditional writes so a stale Cron callback
cannot replace a newer request. Stable per-batch idempotency remains the Cloud
protection against overlapping delivery.
## Runtime Client Paydown Direction (C1 分析, 2026-10-06)

对 `class-cloud-runtime-client.php`（4314 行 / 85 个方法）的结构盘点给出
四条自然接缝，按以下顺序机械拆分，每步一个独立 PR，全程沿用设置页拆分
的方法论（机械搬移 + 失败驱动的契约重定向 + 双文件棘轮 + manifest/
bootstrap/POT/打包/Playground 门禁）：

1. **载荷守卫（纯函数，约 1800 行，第一步，2026-10-06 已落地）**：十五个
   `normalize_*` 请求校验器（wordpress-ai connector/alt-text/image-
   generation、toolbox image/audio/site-ops/media-governance/web-search/
   image-source、agent-feedback、image-context-evidence）及其
   forbidden-key 遍历器、PII 扫描、bounded-text/sanitize 助手，连同它们
   引用的约 40 个场景常量。零实例状态 → 抽为
   `Npcink_Cloud_Runtime_Request_Guards`（静态方法 + 公开常量），客户端
   改为 `Guards::normalize_*(...)` 调用。行为逐字不变；端点允许清单与
   签名内核不动。落地结果：runtime-client 4314 → 2324 行，守卫类 2020 行。
   唯一环境读取点（connector 校验器的 site_id/site_url/addon version）改为
   调用点参数注入；`MEDIA_ARTIFACT_ID_PATTERN` 与两个超时上限常量因传输侧
   共用改为守卫类公开常量，客户端跨类引用。
2. **诊断投影（约 450 行，第二步，2026-10-06 已落地）**：`build_readiness_result`、诊断
   面板构建、severity/next-safe-action/分类与 `redact_support_text`
   → `Npcink_Cloud_Runtime_Diagnostics`（静态，输入为 probe 数组）。
   落地结果：runtime-client 2324 → 1968 行，诊断类 387 行；配置快照与
   `is_configured` 由调用点注入，留守的错误归一化跨类调用
   `redact_support_text`，`MAX_ERROR_MESSAGE_CHARS` 随移。
3. **媒体传输规范化（约 450 行，视纯度并入第 1 步或独立，2026-10-06 已独立落地）**：
   multipart 构建与 upload/ack 响应规范化 →
   `Npcink_Cloud_Runtime_Media_Payloads`（280 行，五个方法本已纯，无参数化改写）。
   落地结果：runtime-client 1968 → 1714 行；`MEDIA_UPLOAD_FORMATS` 与
   `WP_AI_ALT_TEXT_UPLOAD_FORMATS` 因传输侧共用改为公开常量跨类引用，
   `WP_AI_ALT_TEXT_MIN_ARTIFACT_TTL_SECONDS` 随移，`strict_media_timestamp`
   保持私有静态。
4. **签名传输内核（保留不动）**：`request`/`request_raw`/`decode_*`/
   `build_signed_headers`/nonce/traceparent/错误归一化绑定实例凭据，
   留在客户端；`behavior-runtime-endpoint-policy` 与
   `behavior-wordpress-ai-failure-projection` 对 `request`/`decode_response`
   的反射因此无需改动。

契约耦合提示：`tests/static-contracts.php` 有约 179 处引用
runtime-client 源码、其中约 13 处点名 `normalize_*`；搬移后按失败驱动
逐一重定向（与设置页拆分同法）。

## WordPress AI Connector Paydown Direction (C2 分析, 2026-10-06)

对 `class-cloud-wordpress-ai-connector.php`（2767 行）的结构盘点：单个文件
包裹 8 个类，外层 `! class_exists( 'Npcink_Cloud_WordPress_AI_Connector' )`
守卫只含主连接器门面（18–1001，钩子注册、请求级证据状态、错误目录），内层
`AbstractProvider + 两个 Contracts 接口 + ! Provider` 条件守卫（1004–2767）
包裹 7 个卫星类。这与仓库其余部分一类一文件的惯例冲突，是下一个机械拆分
目标。

拆分方向（第一小步，纯文件拆分，零行为变化，2026-10-06 已落地）：

1. 主文件留守 `Npcink_Cloud_WordPress_AI_Connector`（约 1000 行）；7 个
   卫星类各入独立文件，按字母序接线：`alt-text-handoff`(268 行)、
   `availability`(11)、`image-model`(540)、`model-metadata-directory`(156)、
   `provider`(56)、`text-model`(481)、`vision-text-model`(217)。
2. 卫星文件守卫必须按各自身依赖重写：provider 需
   `class_exists( AbstractProvider )`，availability/metadata-directory 需
   各自 `interface_exists`，三个模型类需 `ModelInterface` 及生成接口，
   alt-text-handoff 仅需 `! class_exists( 自身 )`。**不能沿用整块
   `! class_exists( Provider )` 条件**：拆分后第二个文件起会因 Provider
   已定义而跳过自身类定义。
3. 已知坑：
   - `detect_scene_ability_name` 使用 `debug_backtrace` 且自述为 legacy
     兼容桥——按类拆文件不改变调用栈深度（方法原样搬移）；后续在这些
     类内部抽方法会改变栈帧，需先核对它的栈深假设。
   - 4 个行为测试直接 `require` 主文件并读其源码断言
     （alt-text-artifact-handoff、connector-registration、
     provider-acceptance、connector-result）：需逐一补 require 卫星
     文件并按失败驱动重定向源码断言。
   - `tests/wordpress-ai-client-stubs.php` 的空接口 stub 是卫星类定义的
     前提；`maca_load_addon_classes()` 不加载本组文件（无 stub 时守卫
     跳过定义，属预期）。
   - POT 引用与 phpstan 基线（本文件 9 条 / 14 处）随搬移改指向。
4. 去重后续（第二小步，2026-10-06 已落地，#225）：ModelInterface
   五件套样板 ×3 与 `prompt_text`/`extract_text` 重复收敛进抽象基类
   `Npcink_Cloud_WordPress_AI_Scene_Model`；`Availability::isConfigured`
   重复保留（收敛需拓宽主类 API，不值得）。
5. 预存行为加固（#224 顾问审查记录的四条，2026-10-06 已落地）：
   传输可用性预检与任务契约投影移到本地读取/上传之前（fail-fast）；
   上传与请求两侧文件名统一为保留扩展名的 160 字符截断；负数与零
   整数 attachment_id 在 `absint` 归一前拒绝。

## Media Derivative Transport Paydown Direction (C3 分析, 2026-10-06)

对 `class-cloud-media-derivative-transport.php`（2542 行）的结构盘点：
单一全静态类，零实例状态，接缝已盘出，按序拆分：

1. **本地源/上传校验（约 200 行，WP 文件系统绑定，2026-10-06 已落地）**：
   `normalize_upload_file_descriptor`、`is_allowed_upload_file_path`、
   descriptor 系列助手 → `Npcink_Cloud_Media_Source_Validation`（279 行）。
2. **工件描述符验证与已验证传输（约 400 行，2026-10-06 已落地）**：
   `receive_artifact`、`normalize_artifact_descriptor`、
   `normalize_local_proposal_artifact`、`strict_timestamp`、
   `normalize_sha256` → `Npcink_Cloud_Media_Artifact_Verification`（636 行），
   并收口 `$mime_by_format` 逐字重复为单一私有常量；共享上限
   （`MAX_UPLOAD_BYTES`/维度/像素/警告）与标量归一助手随 E 公开，
   `normalize_governance_canary_result` 过渡性公开待第 3 步随迁。
   落地结果：transport 2542 → 1664 行。
3. **治理金丝雀严格校验（约 240 行，纯，2026-10-06 已落地）**：
   `normalize_governance_canary_result` 及其投影 →
   `Npcink_Cloud_Media_Governance_Validation`（281 行，含共享
   `has_exact_keys` 与最低节省基点公开）。
4. **本地提案/优化计划投影（约 300 行，纯，2026-10-06 已落地）**：
   `build_local_proposal_payload`、`build_media_optimization_payload`、
   `media_optimization_plan_from_derivative_payload` 及计划助手 →
   `Npcink_Cloud_Media_Plan_Projection`（707 行，含随迁的
   `validate_request_contract`、`contains_forbidden_secret_fields`、
   `bounded_projection_*` 助手与 `REQUEST_CONTRACT_VERSION`）。
5. **Cloud 任务编排留守**（`dispatch_from_ability_response` +
   `build_media_job_params` + `verified_client`，唯一状态触点为
   `Npcink_Cloud_Addon_Settings`）。落地结果：transport 1664 → 714 行，
   E 对治理类的过渡性跨类调用改指新类，双向耦合关闭。

重复收口（拆分时顺手或独立小 PR）：`$mime_by_format` 映射已在 #227
收敛；两个 artifact descriptor 规范化器的共享校验规则已于 2026-10-06
单源化为七个私有谓词助手（filename_basis 形状、suggested_filename
上界、mime/format 一致、几何、字节上界、警告计数与逐条上界），错误
码/消息/检查顺序逐一保持，验证类棘轮随调升并记录理由。剩余跨文件
重复三项——`strict_timestamp` 与 Image_Model 的 `strict_image_timestamp`
同源（后者多 `+00:00` 偏移要求）、`MAX_IMAGE_BYTES` 与验证类
`MAX_UPLOAD_BYTES` 同值、`receive_artifact` 与 `download_artifact_images`
的 delivery-ack 验证块逐字段平行（跨两个集成面，只在顺手时统一，
不单独开 PR）。

执行顺序：C2 文件拆分先行（每步独立 PR，沿用 runtime 拆分方法论），
C3 在 C2 合并后进行。settings-page 披露标记合并收益实测仅约 40–70 行，
暂缓；phpstan 基线按 2026-10-06 分类维持机会主义收缩，不开专项。

## Split Program Closure (2026-10-07)

The 2026-10 mechanical split program (runtime client, WordPress AI connector,
media derivative transport, settings actions; #220–#230) is closed. The size
ratchets above stay as ceilings, not targets: further splits happen only when
a real change — a new endpoint, integration, or incident — makes a seam
necessary, never to chase line counts. The three recorded cross-file
duplicates stay opportunistic-only. Verification work now follows
`docs/verification-evidence-standard.md` instead of paydown campaigns.

Optional recommendation observation fields reuse `cloud_agent_feedback.v1`.
`class-cloud-agent-feedback-metadata.php` validates only bounded versions,
positions, score buckets, retrieval sources and boolean rerank evidence, plus
the existing source score guard. This pure transport helper keeps the runtime
guard below its existing line cap; it adds no state, configuration or UI.


## 2026-10-17 production acceptance follow-up

The settings-page ceiling moves from 2283 to 2299 lines for one bounded
missing-official-AI-plugin notice. It reuses the generation-link renderer in
Overview and Advanced, reads local activation/file evidence, and links to
WordPress-owned installation/activation. No new setting, persistence, endpoint,
automatic installation, AI request, or local runtime control is added.

2026-10-19: the runtime-client ceiling increases by nine lines for the shared
pre-signing availability guard on runtime execution and nightly retry. This
adds no settings, registry, state, HTTP request, or scheduler ownership.

2026-10-19 advanced-media pause: four additional runtime-client lines reject
media-job POST before signing; two transport lines reject before source uploads;
two projection lines reject new optimization plans. These bounded guards preserve
shared artifact delivery, historical results, configuration and all implementations.

2026-10-19 admin simplification / reversible pauses: settings-page ceiling moves
from 2299 to 2337 lines. Overview hides Runtime allowance when inspection is
paused; Advanced collapses secondary tabs into one page, folds entitlement
limits, and hides WordPress AI generation-test entry points through
`Npcink_Cloud_Discoverability_Pause::is_admin_surface_paused`. No classes,
actions, options, or public APIs are removed.

2026-10-19 admin CSS continuity: settings-page ceiling moves from 2337 to 2350 for Advanced single-page stack wrappers and enqueue hardening (page query detection + admin.css filemtime cache-bust). Adds scoped stack CSS; does not remove secondary-tab helpers or public APIs.

2026-10-19 admin CSS dual-enqueue fix: settings-page ceiling moves from 2350 to 2375 for symlink-safe `plugin_file_for_asset_url()`, cloud-page-only CSS enqueue (no PARENT_MENU_SLUG match), and skip-enqueue-when-unreadable. Paired with governance-core Admin_Page enqueue narrowed to MENU_SLUG so Cloud no longer loads Core `admin.css?ver=0.3.0`.

2026-10-19 plugins_url worktree 404: settings-page ceiling 2375→2385 for `plugins_dir_slug()` + `asset_url()` (single-arg plugins_url; never pass realpath/__FILE__). Local symlink must stay `npcink-cloud-addon` → `/Users/muze/gitee/npcink-cloud-addon`, not a git worktree folder.

2026-10-19 P0 settings declutter: settings-page ceiling → 2450 for portal_url/render_portal_link, summary vs Overview notice split (drops off banner), Connected without synonym health label, dashicons Retry/Portal icon buttons + CSS.

2026-10-19 restore Advanced secondary tabs: settings-page ceiling → 2430; `render_advanced_page` again uses `render_secondary_tab_navigation` + `?view=` (service|checks|connection) instead of single-page stack.

2026-10-19 entitlement details layout: settings-page ceiling → 2440; remove Usage limits `<details>` disclosure; heading row aligns Cloud credit link right; ownership copy moves to link title / screen-reader text.

2026-10-19 UI densify pass (figs 1–5): settings-page ceiling → 2500; Overview icon Portal + compact plan chip; Features short switches + flat privacy; Site Knowledge badge/short status + Cloud icon; Advanced entitlement untitled + More limits; panels max-width 720px.
2026-10-19 four-tab alignment: settings-page ceiling 2500→2450 after removing unused secondary-tab renderer and tab_view_url; copy and 720px column alignment do not add lines.
2026-10-19 tiered discoverability: settings-page ceiling stays 2450; Overview entitlement keeps Renews / AI credit period visible and folds remaining limits into More limits; Checks folds Hosted Runtime, capability rows, and readiness history into Check details; Knowledge base status title uses screen-reader-text; empty entitlement and healthy Updates-will-appear copy stay unrendered.
2026-10-20 failure-alert CTAs: settings-page ceiling 2450→2600 for cause-specific Knowledge base repair links, readiness next-action markup, visible entitlement Retry, Checks delivery actions, and Connection-tab Check connection when service needs attention.
2026-10-20 Checks generation-test modal: settings-page ceiling 2600→2680 for in-page invoke dialog (reuse AI Ability Explorer `ai_ability_explorer_invoke`), folded text actions, and Advanced Explorer corner link.

2026-10-20 connection swap-account: settings-page ceiling 2680→2760 and settings-actions 1175→1230 for Connected Cloud identity projection, change-account confirmation that clears local credentials while preserving Base URL, and shared disconnect/change-account wipe helper. No Site ID / Key ID surfaces; account selection stays Cloud-owned.
2026-10-21 connection layout: settings-page ceiling 2760→2800 for a single card per state (status line + one main action + one description), shared status-line helper, folded More information / bottom Advanced settings, and hiding the tab bar before verification.
2026-10-21 checks-simple: settings-page ceiling 2760→2800 for a plain-language Checks status line with one main action, and folding existing check tables/tests into a default-collapsed "More information (for contacting support)" with Copy information. Status is projected only from stored Cloud-reported state; no new WordPress-side judgments.
2026-10-21 checks-simple on connection layout: settings-page ceiling 2800→2840 so the Checks status reuses the shared card and status-line helper.
