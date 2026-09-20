# Local business validation evidence — 2026-09-20

Status: deterministic local journey, natural delivery, upgrade/rollback, and a
zero-cost real local-Provider semantic checkpoint passed. Production pilot
readiness and merged Cloud acceptance remain open.

## Subject and scope

- Local origin: `http://magick-ai.local`.
- Mounted Addon: this repository at `61c7f64` (branch
  `codex/local-business-validation-evidence-20260920`).
- WordPress 7.1.1, WordPress AI 1.3.0, Addon 0.2.0.
- Local PHP 8.2.29; the old PHP 8.5.3 executable and old socket defaults do not
  exist on this installation. Explicit inspected environment overrides were used.
- Mode: local fake Provider. No paid generation was dispatched. The Cloud
  capability read used the accepted M4 revision `c31a7db464450b86b274fb3047a2d59479edc39f`.
  The M4 runtime-profile admin state was temporarily opened with a healthy
  text candidate for the fake run, then closed again through the same audited
  admin endpoint. The final capability snapshot is intentionally
  `text_generation=unavailable` / `no_eligible_model`, so no accidental real
  request can proceed after the test.

## Findings and evidence

The old preflight passed because the connector was enabled and credentials were
verified. Two browser attempts then returned `unsupported_model` for both title
requests. Neither reached the title review dialog. Zero pre-save fixture
writes were observed. Both runs verified removal of their own temporary draft,
authentication session, fake filter/option, and fixture quality state.

A subsequent bounded capability read reported `text_generation=unavailable`,
`reason_code=no_eligible_model`, and `entitlement_state=configured`, with
`checked_at=2026-09-20T09:59:54.177636Z`. This identifies model-discovery
readiness as the failed prerequisite; it does not establish why the upstream
Cloud model configuration has no eligible candidate.

The old runner also treated any failed first title response as synthetic
Provider-failure evidence. That assertion was unsound: `unsupported_model`
can occur before the fake HTTP transport runs.

## Correction and verification

- Preflight now reads the normalized cached text capability without refreshing
  or overriding it. Missing, expired, failed, and unavailable evidence fails
  before fixture creation or browser startup, in fake and real modes alike.
- The first synthetic failure must have exactly one matching failed title
  attempt in the fixture's fake transport evidence. An unrelated ability error
  cannot satisfy it.
- JavaScript readiness regressions cover configured capability, missing and
  unavailable capability, expiry, refresh failure, entitlement mismatch, and
  real versus absent/malformed synthetic failure evidence.
- `composer run test:all`, `composer run check:js`,
  `composer run check:wporg`, and `composer validate --no-check-publish` pass.
- With the inspected Local PHP and MySQL socket supplied explicitly,
  `composer run release:verify` also passed: the release ZIP and manifest were
  verified and strict Plugin Check returned `ok`.
- The clean-environment checks passed: the WordPress Playground smoke activated
  the packaged Addon and verified the real ordered reconciliation cursor; the
  `stable-primary` (WordPress 7.0.4 / AI 1.3.0 / PHP 8.0) and
  `stable-regression` (WordPress 7.1.1 / AI 1.2.0 / PHP 8.2) compatibility lanes
  both reached their routes and passed Addon discovery and boundary checks.
- Before the successful run, the local preflight exited before creating a
  fixture with `reason=snapshot_expired` after the earlier snapshot aged out.
  That negative run proved the readiness gate; the refreshed, explicitly
  configured snapshot was then used for the positive deterministic run.
- After the M4 text capability was made temporarily eligible through the
  existing runtime-profile admin contract, the full fake browser journey
  passed. It recorded one synthetic title failure, two successful title
  responses, one summary, and one rewrite; all five requests were intercepted
  locally and marked `transport_preempted=true`.
- The passed journey verified the visible title review, regeneration, manual
  edit before insert, summary review, whole-paragraph rephrase review, and the
  explicit save boundary. It observed `pre_save_post_writes=0`,
  `explicit_save_writes=1`, `revision_delta=1`, unchanged non-target sentinel
  blocks, and complete fixture/session/fake-provider cleanup.
- The `cancel-reload` browser scenario also passed: it completed the same
  review steps, closed and reopened the dirty editor before saving, observed
  `pre_save_post_writes=0` and `explicit_save_writes=0`, restored the original
  title and block content, and completed the same cleanup.
- The `permission-denied` browser scenario passed: a disposable author account
  attempted to open the administrator-owned draft and received the WordPress
  permission-denied screen. The run observed zero ability responses, zero
  pre-save or explicit post writes, an unchanged protected draft, zero fake
  transport events, and deletion of both the temporary author and draft.
- A no-paid-provider Cloud execution checkpoint was attempted with the M4
  Ollama catalog candidate. The audited profile write succeeded, but the
  signed capability projection correctly remained `no_eligible_model` because
  this M4 runtime has the Ollama adapter disabled (`base_url` empty and catalog
  disabled). No real request was dispatched. The temporary binding was removed
  and the local capability cache was refreshed back to the same fail-closed
  state.
- The natural-delivery inspector was run before and after an ordinary Local
  front-end request that advanced the scheduled WP-Cron timestamp. The
  customer-journey buffer still contained 134 events and the read-only Cloud
  journey summary reported zero events. Natural journey delivery therefore
  remains unproven and is tracked as an integration issue; the buffer was not
  manually flushed or cleared.
- A blocking diagnostic confirmed that the pending transport is signed
  `POST /v1/customer-journey/events`; the diagnostic intercepted it before
  external transfer and verified the buffer count stayed at 134. The next
  investigation should inspect the scheduled callback's actual HTTP result,
  rather than treating the observability status as journey-delivery evidence.
- A single metadata-only signed transport probe then returned
  `accepted_count=1`, `stored_count=1`, `duplicate_count=0`; the read-only Cloud
  summary observed one event. This proves the endpoint and credentials work,
  while the natural Cron callback remains the unresolved part.
- The fake browser transport was tightened to intercept customer-journey
  uploads as well as observability uploads. A full fake browser rerun removed
  16 journey events created by that run and preserved the pre-existing 134
  events exactly. The final M4 profile state and local capability snapshot were
  restored to `no_eligible_model` afterward.
- The new `concurrent-tabs` browser scenario then opened the same disposable
  draft in two editor tabs, issued title requests concurrently, delayed one
  bounded fake response, and completed the retry in the affected tab. Both tabs
  displayed non-empty suggestions, the shared draft had zero pre-save writes,
  and the fake transport recorded three isolated title attempts. The temporary
  draft, session, fake plugin, fake option, and all seven run-owned journey
  events were removed afterward. A read-only inspector confirmed that the
  pre-existing buffer remained exactly 138 events.
- This is deterministic UI and local data-path evidence only. It does not prove
  Cloud execution, signed event ingestion, Provider output quality, or natural
  WP-Cron delivery.
- Playground is not applicable to this test-tooling change: bootstrap,
  credentials, public PHP seams, and the supported version matrix are unchanged.

## Remaining sequence

1. Inspect Cloud model eligibility and refresh capability through the existing
   connection check. Preserve unavailable evidence until its cause is resolved;
   do not overwrite the cache or enable an unreviewed Provider to pass a test.
2. Repeat the isolated fake browser journey on the reviewed revision, then the
   opt-in quality-correlation lane when monitoring is enabled.
3. Execute and record the remaining failure cases in the
   [business plan](local-business-validation-plan-v1.md). Cancel/reload,
   permission denial, and concurrent delayed responses now have deterministic
   runner evidence.
4. Keep the signed-ingestion evidence in the separate integration lane and
   reuse the natural-delivery checkpoint below for future revisions. Local
   fake transport cannot replace either proof.
5. Repeat the real-Provider checkpoint after any Cloud capability or Addon
   transport change. The zero-cost local Ollama run and the release-shaped
   upgrade/rollback rehearsal are now recorded; production and natural-traffic
   pilot readiness remain unproven.

## Isolated signed-delivery checkpoint (2026-09-21)

- A disposable WordPress database and filesystem root were created from the
  current Local installation. Its journey buffer started empty, the site URL
  was `http://127.0.0.1:8090`, and the operator-declared cohort was
  `local-fresh-20260920`. The original `magick-ai.local` database and its 138
  buffered events were not modified.
- A metadata-only title journey event without a Cloud `run_id` was sent through
  the real signed customer-journey client. Cloud returned `ok=true`,
  `sent_count=1`, `stored_count=1`, and `duplicate_count=0`; the disposable
  local buffer became empty. This is the first isolated signed-ingestion
  evidence.
- A separate disposable event carrying an unowned `run_id` was rejected with
  `run_id must reference a run owned by the authenticated site`, confirming the
  Cloud ownership guard. It was removed only from the disposable database.
- An HTTP `wp-cron.php` trigger reached the temporary PHP built-in server and
  spawned the non-blocking loopback request, but the single-process server did
  not execute the scheduled callback reliably. This is harness evidence only;
  it is not natural WP-Cron delivery evidence. A real Local/FPM or equivalent
  multi-process web lane is still required before claiming that criterion.

## Multi-process natural WP-Cron checkpoint (2026-09-21)

- A second disposable database and filesystem root were served by a real
  Nginx + PHP-FPM pair on `http://127.0.0.1:10011`. The original Local sites,
  source tree, and the original `magick-ai.local` buffer were not modified.
- A one-shot `npcink_cloud_addon_flush_observability` event with a distinct
  argument was scheduled in the disposable database. After the transient Cron
  lock was cleared, an ordinary HTTP GET to `/wp-cron.php` reported one ready
  event; the MU-plugin trace recorded the real
  `npcink_cloud_addon_flush_observability` action; and the request exited with
  no PHP error.
- The disposable customer-journey buffer was `0`, the addon status recorded
  `last_projection_at=2026-09-20T16:53:14+00:00` and `last_upload_ok=true`, and
  the Cron lock was released. The recurring event was rescheduled for the
  following interval. This closes the natural multi-process WP-Cron delivery
  criterion for the current revision; repeat it after changes to scheduling,
  buffering, or transport code.

## Release-shaped upgrade and rollback checkpoint (2026-09-21)

- The exact `0.2.0` release ZIP was built and verified with the release
  manifest (`verified_files=33`, SHA-256
  `aebe15d1b18db4d324224ab92f3c47b0e1b4467fefd6fb21f51a7f18d1442cb0`).
- In a disposable WordPress 7.1 database, Addon `0.1.3` was activated with a
  complete legacy plaintext credential option. Installing the `0.2.0` package
  upgraded and activated the plugin successfully. The raw legacy option stayed
  untouched, while the new settings reader returned `configured=false`,
  `verified=false`, and `monitoring=false`. This is the intended security
  boundary: 0.2.0 ignores legacy plaintext credentials and requires the site to
  reconnect; it does not silently accept or migrate them.
- The disposable site was then rolled back by installing the `0.1.3` package.
  The old plugin activated successfully and read the legacy option as
  `configured=true`, `verified=true`, and `monitoring=true`. This confirms the
  rollback path while making the version-dependent credential behavior
  explicit. No Provider request or production site was involved.

## Real local Ollama Provider semantic checkpoint (2026-09-21)

- The Cloud eligibility projection was corrected in candidate revision
  `8c53ee15384ff564c094460021821cd25f2d2c70`. The regression was that the
  entitlement route considered only base service providers, while the runtime
  had a database-configured `ollama-m4` connection. After the fix, a fresh
  capability read returned `text_generation=configured` with
  `provider_id=ollama-m4`; image and vision remained unavailable as expected.
  The M4 state is a clean candidate preview, not a merged `master` acceptance.
- A three-call Provider ledger was opened with a maximum of three calls and
  closed with `claimed_calls=3`, `remaining_calls=0`, and
  `close_reason_code=completed`. The calls were title generation, content
  summary, and content rewrite. They used the local Ollama adapter with model
  `ollama-m4/qwen3.5:9b`, cost `0`; no paid external Provider was contacted.
- The real browser journey completed on WordPress 7.1.1, WordPress AI 1.3.0,
  and Addon 0.2.0. Title, summary, and rewrite suggestions were visible and
  saved exactly. It observed `pre_save_post_writes=0`,
  `explicit_save_writes=1`, `revision_delta=1`, and unchanged non-target
  sentinels. The quality-correlation evidence was validated with six journey
  events, three sessions, three presented generation events, three observed
  outcomes, zero pending events, zero invalid content records, and no forbidden
  fields. The disposable draft and authentication session were deleted.
- The three user-facing Cloud runs were observed in M4 as:
  `run_673b328181e84bd9b24ab5d1d3267de9` (`wp-ai.short-text`),
  `run_31bc8984ba0b49cda63a90b7ea109878` (`wp-ai.editorial`), and
  `run_1e45514c835e45a38e8871d51832c218` (`wp-ai.editorial`). Each succeeded
  through `npcink-cloud/connector-runtime` using `ollama-m4`; Provider call
  records reported no error and zero cost.
- This checkpoint proves the local configured-Provider business path and the
  Cloud capability/readiness contract. It does not prove merged-branch
  acceptance, production credentials, external Provider billing, or a
  natural-traffic pilot.

## Reusable lesson

Keep connection identity, current model capability, actual transport execution,
visible suggestion, and reviewed persistence as separate acceptance facts.
Assert that an injected fault was reached before labeling a failure as its
expected result. Preserve cleanup evidence even when the business flow fails.
