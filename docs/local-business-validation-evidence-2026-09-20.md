# Local business validation evidence — 2026-09-20

Status: partial; the full business journey has not passed on this revision.

## Subject and scope

- Local origin: `http://magick-ai.local`.
- Mounted Addon: this repository, based on `d8317e0`; initially only acceptance
  documentation was uncommitted. The subsequent fix changes test tooling only.
- WordPress 7.1.1, WordPress AI 1.3.0, Addon 0.2.0.
- Local PHP 8.2.29; the old PHP 8.5.3 executable and old socket defaults do not
  exist on this installation. Explicit inspected environment overrides were used.
- Mode: local fake Provider. No paid generation was dispatched. Cloud runtime
  execution, current M4 revision, and signed event ingestion were not validated.

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
- The actual local preflight now exits before creating a fixture with
  `reason=snapshot_expired`, as the earlier snapshot has aged out. This proves
  the negative readiness gate, not a working business flow.
- Playground is not applicable to this test-tooling change: bootstrap,
  credentials, public PHP seams, and the supported version matrix are unchanged.

## Remaining sequence

1. Inspect Cloud model eligibility and refresh capability through the existing
   connection check. Preserve unavailable evidence until its cause is resolved;
   do not overwrite the cache or enable an unreviewed Provider to pass a test.
2. Repeat the isolated fake browser journey on the reviewed revision, then the
   opt-in quality-correlation lane when monitoring is enabled.
3. Execute and record the additional cancel/reload, permissions, concurrent
   responses, and failure cases in the [business plan](local-business-validation-plan-v1.md).
4. Obtain full signed-ingestion and natural-delivery evidence in the separate
   integration lane. Local fake transport cannot prove these.
5. Complete a specifically budgeted real-Provider semantic checkpoint and the
   release-shaped rehearsal. Production and natural-traffic pilot readiness
   remain unproven.

## Reusable lesson

Keep connection identity, current model capability, actual transport execution,
visible suggestion, and reviewed persistence as separate acceptance facts.
Assert that an injected fault was reached before labeling a failure as its
expected result. Preserve cleanup evidence even when the business flow fails.
