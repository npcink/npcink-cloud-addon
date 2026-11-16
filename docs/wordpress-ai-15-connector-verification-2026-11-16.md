# WordPress AI 1.5 connector verification — 2026-11-16

Status: local candidate, not merged or deployed. AI 1.5 Chinese UI coverage is
still incomplete; do not treat connector compatibility as complete UI acceptance.

## Scope and boundary

The three AI Client models now check the official optional Connector Approval
feature before uploads, signing or HTTP. Native caller identification, approval
and pending stores remain authoritative; there is no Addon approval store/UI.

Validated Ability execution context replaces the PHP implementation-class
backtrace map. Nested identities are isolated and text context is single-use.
WordPress 7.1 releases context on callback errors as well as success. Older
6.9/7.0 versions retain success-hook and REST/shutdown cleanup; direct in-process
legacy callback failures before model invocation are not a proven boundary.

Block container labels and revision change summaries have reviewed local task
contracts and output validation. They reuse existing Cloud generation/analysis
families. No Cloud source, per-feature Cloud scene registry, official prompt,
WordPress approval ownership or write ownership changed. Reference-image edits,
TTS, type-ahead and paused features are outside this batch.

## Local gates

Passed: Composer validation, `test:all`, PHPStan, `check:wporg`, `check:js`,
POT freshness, Playground bootstrap, the new WP 7.1.4 / AI 1.5.0 / PHP 8.4
compatibility lane, release packaging/manifest verification, strict final-ZIP
Plugin Check, and `git diff --check`.

`ai:i18n:check` failed on the installed AI 1.5.0: 1,313 discovered ai-domain
strings, 681 shim translations and 255 UI coverage gaps. The shim, localization
JavaScript and audit script are unchanged from this branch's base `e57cf34`.
This batch does not absorb another active localization task. Consequently the
complete `release:verify` gate is not green and this candidate is not release-ready.

The final source and regression tests were reviewed locally for context
isolation, approval denial before spend, result ownership and boundary safety.
No extra paid AI review was initiated after the four-call implementation cap.
The legacy publisher requests auto-merge and has no draft option; publication
must preserve the checked-in PR body contract and remain draft while this gate
is unresolved, rather than request automatic merge.

## Local runtime and browser evidence

The site used WordPress 7.1.4, AI 1.5.0 and PHP 8.5.12 through its existing
loopback Cloud tunnel. These are local observations, not M4 accepted,
production, or human acceptance evidence.

| Check | Result / run |
| --- | --- |
| Native approval rejection | HTTP 403, `wpai_connector_not_approved`, official pending entry captured, zero outbound requests; no persisted approval mutation |
| Block label | HTTP 200, `Connector Approval Process`; `run_6b12aa8d704e40748e486295b046fd17`; unchanged content snapshot |
| Initial revision experiment | Cloud succeeded but Addon rejected a public-schema `truncated` field; `run_4a355534a189470a970142b7a44aaf23`; validation subsequently fixed |
| Revision retest | HTTP 200 and quality passed; `run_16352b8d51df42fc90e5b34e2cee4bca`; global write snapshot changed during concurrent editor autosave, so this is not clean no-write proof |
| Revision response replay | Same successful response replayed locally, zero provider calls, zero post/postmeta SQL writes; deliberately incorrect model IDs/truncation replaced by official local revision fields |
| Native title adoption | Suggestion displayed, reviewed Insert, Save draft, refreshed persistence; `run_46381e72128f4add803f10a7af84d206` |

The retained test post is draft 283293; compared revisions are 283294/283295.
Its saved title is `WordPress Controls Connector Approval and Post Saves`.
Summary and rewrite had API evidence in the preceding audit; their native
browser adoption was not verified in this batch. One title and two small new
feature fixtures do not prove every AI settings toggle or long-form quality.

The temporary candidate-only MU selector and plugin symlink were removed.
The site's original dirty source mount was preserved. The candidate worktree
and final ZIP remain available for review; installing this branch is a separate
rollout step. Reverting this focused commit restores prior connector behavior.

## Bounded model spend

Implementation: four hosted text calls using `openai/gpt-6.1-sol`, 3,486 text
tokens including the failed validation experiment, plus 89 local embedding
tokens. Including the preceding audit: seven hosted text calls, 5,182 text
tokens and 142 local embedding tokens. The replay was not another provider
call. Cloud cost fields were zero; actual upstream charges were not reconciled.
No further model requests are needed for this handoff.
