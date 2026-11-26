# WordPress AI media acceptance safety follow-up — 2026-11-17

Status: candidate changes verified locally; not deployed or released. The site
still mounts the local `npcink-cloud-addon` checkout, not this worktree.

## Change envelope

- Repository: `npcink-cloud-addon`; focused on development acceptance safety,
  ALT output validation and image-delivery diagnostics.
- Public contracts: existing CLI acceptance now requires an explicit Ability
  allowlist and maximum runtime submissions; result shape gains `call_budget`.
  No runtime endpoint, credential, setting or Cloud configuration changes.
- Runtime changes: reject malformed ALT before host adoption; retain only
  allowlisted image-delivery stage metadata associated with the same run.
- Excluded: Cloud routing, prompts, provider billing, approvals, WordPress
  content writes, image editing, TTS and typeahead implementation.
- Rollback: revert these candidate changes; no data or settings migration.
- Cross-repository compatibility matrix: no release or cross-repository
  implementation milestone in this follow-up. Update Cloud's recommended
  minimum when releasing the Addon; no runtime version rejection.

## Findings and limits

The earlier live validation exceeded the authorized ten runs: fourteen unique
model runs were recorded (twelve OpenAI text, one SiliconFlow image, one Ollama
vision). That incident remains open in the original validation ledger; actual
fees were not reconciled. This follow-up added **zero model calls**.

The CLI guard defaults to zero outbound requests, requires a bounded selection,
counts outbound runtime submissions before dispatch, and stops on contract,
quota-preflight, HTTP or output failures. It blocks nested over-budget calls,
runtime retry, media-job and unexpected destination paths. Its maximum is
per invocation and does not measure Cloud-internal provider fallback attempts
or charges. Restarting the CLI does not renew a user's authorization.

The captured vision result was truncated evidence JSON, not usable ALT. The
candidate rejects it, complete JSON objects/arrays, markup, multiline text,
invalid UTF-8 and text longer than 240 Unicode characters. Plain Chinese and
English ALT remain accepted. This prevents unsafe adoption; it does not repair
Cloud's generation quality or prove visual accuracy.

The existing image run `run_b5ae5e4cf3094456a10f36d2735bbc5d` subsequently yielded
one native SDK candidate from its existing verified artifact. The original UI
failure was not reproduced, so its root cause is **not fixed or established**.
The candidate adds contract/download/bytes/ACK/ACK-validation/conversion stage
diagnostics. Recorded bytes also passed the candidate's native SDK offline
replay: 1,800,923 bytes, SHA256
`bdbbd4fa210797830dd3d79675d8ad52451bb080d58f2b829998424d62765efd`.
Replay TTL and ACK were synthetic; this is not another live delivery receipt.

Installed MCP Adapter STDIO wire handling negotiated protocol `2025-11-25` and
executed Toolkit site-info read-only. A registered but unexposed ability and an
unauthenticated call were rejected. No outbound HTTP was attempted. This checks
the installed host/Adapter path, not an external client's HTTP authentication.

The existing editor loaded the WebMCP bridge and fourteen tool definitions.
Its `editor-get-document` callback succeeded and editor dirty state stayed false.
Neither `document.modelContext` nor `navigator.modelContext` exists in the
current Edge host: native browser-agent discovery/execution remains unverified.
No tool registration polyfill, editor writes, saves or publishes were performed.

Image editing remains unsupported by this connector, TTS is paused before
transport, and typeahead has no Addon task contract. MCP/WebMCP do not require
new Cloud scenarios for local tool execution.

## Verification

Deterministic suites, PHPStan, WordPress.org static checks, catalog consistency,
release packaging/verification and the disposable Playground smoke passed.
The native Local MySQL/AI 1.5 SDK replay used recorded model output and mocked
all HTTP. Failure injection distinguishes image download, bytes, ACK and ACK
verification, and proves invalid bytes are never acknowledged.

JavaScript and fixed UI copy were not changed, so browser localization and JS
change gates do not apply. No new exception or placeholder translation strings
were introduced. Live provider acceptance remains incomplete; offline replay
cannot close it. Before publication, observe the complete PR check rollup and
perform the repository's advisory review under a separately authorized budget.

Durable local evidence is under
a local visualization directory for the 2026-11-17 WordPress AI 1.5 evidence.
See `SAFETY-FOLLOWUP.md`, the native replay receipt, MCP receipt and gate logs.
