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

- `includes/class-cloud-runtime-client.php` — 4314 lines
- `includes/class-cloud-settings-page.php` — 2207 lines (was 3280 before the
  2026-10-04 handler split into `class-cloud-settings-actions.php`)
- `includes/class-cloud-settings-actions.php` — 1106 lines (settings request
  handlers and their transient state; the render class stays a projection)
- `includes/class-cloud-wordpress-ai-connector.php` — 2767 lines
- `includes/class-cloud-media-derivative-transport.php` — 2542 lines

The ratchet is a ceiling, not a target. Raise a limit only together with a
documented reason here; prefer paying it down instead. The settings-page
handler split has landed: `Npcink_Cloud_Settings_Actions` owns the twelve
admin-post/wp-ajax request handlers, the authorization flow machinery, and the
shared transient state (notices, permission feedback, readiness results,
consent prompt); the page class renders and reads that state through the
public accessor seam. Further paydown direction for the remaining render class
is collapsing duplicated disclosure markup behind shared partials, keeping
`docs/admin-surface-standard.md` tab and copy rules authoritative.

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
  HTTP stubs, settings seed helpers, and media derivative fixtures.
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

1. **载荷守卫（纯函数，约 1800 行，第一步）**：十五个
   `normalize_*` 请求校验器（wordpress-ai connector/alt-text/image-
   generation、toolbox image/audio/site-ops/media-governance/web-search/
   image-source、agent-feedback、image-context-evidence）及其
   forbidden-key 遍历器、PII 扫描、bounded-text/sanitize 助手，连同它们
   引用的约 40 个场景常量。零实例状态 → 抽为
   `Npcink_Cloud_Runtime_Request_Guards`（静态方法 + 公开常量），客户端
   改为 `Guards::normalize_*(...)` 调用。行为逐字不变；端点允许清单与
   签名内核不动。预期 runtime-client 降至约 2400 行。
2. **诊断投影（约 450 行，第二步）**：`build_readiness_result`、诊断
   面板构建、severity/next-safe-action/分类与 `redact_support_text`
   → `Npcink_Cloud_Runtime_Diagnostics`（静态，输入为 probe 数组）。
3. **媒体传输规范化（约 450 行，视纯度并入第 1 步或独立）**：
   multipart 构建与 upload/ack 响应规范化。
4. **签名传输内核（保留不动）**：`request`/`request_raw`/`decode_*`/
   `build_signed_headers`/nonce/traceparent/错误归一化绑定实例凭据，
   留在客户端；`behavior-runtime-endpoint-policy` 与
   `behavior-wordpress-ai-failure-projection` 对 `request`/`decode_response`
   的反射因此无需改动。

契约耦合提示：`tests/static-contracts.php` 有约 179 处引用
runtime-client 源码、其中约 13 处点名 `normalize_*`；搬移后按失败驱动
逐一重定向（与设置页拆分同法）。
