# Verification Evidence Standard

Status: active standard for `npcink-cloud-addon`.

Purpose: state which evidence tier each change type owes and how long
evidence stays valid, so release readiness never depends on memory or on the
silent reuse of stale results. This standard complements
`cloud-addon-complexity-budget.md` (which defines the tiers) and `AGENTS.md`
(which defines the gates). It adds no boundary and no product control.

## Evidence Tiers

The tiers keep the meaning they have in `cloud-addon-complexity-budget.md`:

- Tier A — deterministic: `composer run test:all` (lint, contract tests,
  boundary search), `composer run stan`, `composer run check:wporg`, and
  `composer run check:js` when JavaScript changed.
- Tier B — disposable compatibility: `composer run smoke:playground`.
- Tier C — real integration: Local MySQL/Cloud smokes, browser smokes, and
  the acceptance suites (`composer run smoke:wp-ai-*`,
  `composer run acceptance:wp-ai-provider[:quality]`,
  `composer run local:journey:inspect`), each recorded with date and
  environment.
- Tier D — release and production: `composer run release:verify`, the
  WordPress.org release gate, and post-release production observation.

## Change-Type Matrix

| Change | Tier A | Tier B | Tier C |
| --- | --- | --- | --- |
| Any `includes/`, `assets/`, plugin main-file, or `uninstall.php` change | required | required when bootstrap, activation, the public connector API, the default credential/connector state, or the WordPress/PHP compatibility baseline is affected | required for the touched surface: transport and signing changes owe the media and WordPress AI behavior suites plus a Local Cloud smoke; settings and admin flows owe a browser smoke of the changed flow; the WordPress AI provider surface owes provider acceptance |
| `tests/` only | required | not applicable — record why | not applicable — record why |
| `docs/` only | required (static contracts read docs) | not applicable — record why | not applicable — record why |
| CI job or `scripts/` gate change | required | as triggered by scope | observe one complete check rollup on the PR before merge |
| Composer platform, PHP floor, or WordPress baseline change | required | required | re-run the WordPress AI compatibility lanes and record the rollup |

"Not applicable — record why" keeps the existing PR-template discipline: the
reason is written into the PR's verification record, never silently omitted.

## Evidence Freshness

- Tier C evidence expires when the surface it covers changes, or after 30
  days, whichever comes first. A release must not cite expired evidence.
- A release record must cite, with dates: the latest Tier B playground
  result, the latest Tier C result for each touched surface, and the
  production observation point that followed the previous release.

## Non-AI Evidence Anchor

This repository is developed AI-first: code is AI-written and reviewed by an
advisory AI gate. To keep that loop honest, every published release must
include at least one evidence item produced outside the loop — a human-run
acceptance record, a production observation, or a verified user report —
with its date. A release supported only by AI-written code plus AI-advisory
review is not releasable, even when every automated gate is green.

## Relation To Existing Rules

- `AGENTS.md` stays the gate authority; this standard only classifies which
  tiers a change owes and when evidence expires.
- `cloud-addon-complexity-budget.md` keeps the tier definitions, including
  the rule that a passing playground run is never a substitute for a higher
  tier.
