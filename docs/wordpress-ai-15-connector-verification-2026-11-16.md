# WordPress AI 1.5 connector verification — 2026-11-16

Status: local candidate, not merged or deployed. AI 1.5 Chinese UI coverage is
still incomplete; do not treat connector compatibility as complete UI acceptance.

## Omission-review corrections

The follow-up review reproduced and corrected three defects: an internal REST
request erased its caller's Ability identity; nested ALT generation overwrote
the outer attachment input; and a shared last-approval-denial value could
replace an unrelated REST error. REST entry now suspends and isolates the
caller context, then restores the matching caller on completion. ALT input and
single-use state live in the same Ability frame. A dedicated SDK exception
carries approval origin through the host wrapper, without mutable denial state.

Zero-model regressions passed in both WP 7.0.4 / AI 1.4.0 / PHP 8.0 and
WP 7.1.4 / AI 1.5.0 / PHP 8.4 compatibility lanes. Each lane executed 13 native
Ability/REST checks, covering isolation, restoration, REST-contained callback
failure, nested ALT attachment identity and consumption. These are execution
checks, beyond plugin/route discovery; they do not prove the legacy direct
in-process failure case described below.

On the local WP 7.1.4 site, separate native probes confirmed restored outer
identity and attachment, an empty final context and zero outbound requests.
The real SDK exception converter and REST dispatcher retained approval 403,
independent validation 422 and an outer nested-request error 409. No model was
called for these corrections. Local regression, PHPStan, packaging and strict
Plugin Check were repeated after the fixes; no additional paid review was run.

Signed read-only Cloud queries confirmed one accepted feedback event for
`editor_assist` / `wordpress_ai_editor`. The summary endpoint exposes aggregates,
so this does not prove association with the specific title run below. No new
Cloud endpoint or source change was introduced to fill that evidence gap.

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
The separately mounted dirty checkout had 180 UI gaps at the review checkpoint;
that is a different source tree, not this candidate's 255-gap result. Neither
result establishes final browser coverage.

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

## Integration follow-up — 2026-11-17

The 255-gap candidate and separate 180-gap settings work above are historical
checkpoints. The candidate now includes the reviewed settings mappings and
further fixed AI 1.5 UI translations, plus bounded DataViews support for the
MCP and Ability Explorer screens. The installed-plugin strict fixed-UI audit
now reports zero gaps. Dynamic ability/schema/prompt copy stays excluded, and
this audit does not claim complete runtime error localization.

The original dirty checkout is preserved. Native acceptance uses a temporary
candidate mount and an isolated Chinese draft. A local-only transport guard
accepts only that fixture, replays deterministic cases and caps real summary/
rewrite requests at two in total. Synthetic telemetry uploads are blocked
until fixture-scoped cleanup. Native evidence, final package identity and
remaining limits will be recorded in the integration handoff; source success
alone does not mark this draft PR merged, installed permanently or released.

## Native integration acceptance — 2026-11-17

The candidate was mounted only for a disposable local draft (post 283298) and
then the original `/Users/muze/gitee/npcink-cloud-addon` symlink target was
restored. After resetting the local PHP opcode cache, the candidate path was
confirmed by reflection before the final replay checks.

Two real hosted text calls were used, exactly within the approved cap: Chinese
summary (`run_112f5ea0f2de4965b33826cd0684fabf`) and Chinese rewrite
(`run_d8ddccde86b24d28a352a7a9af71ac42`, 804 total tokens). The summary was
shown in the native summary block. The rewrite preview was shown in the native
“建议替换内容” dialog, accepted explicitly, and the first paragraph changed
while the second paragraph and sentinel remained intact. Before Save draft,
the database content hash was unchanged; after Save draft and refresh, the
summary, rewritten paragraph and sentinel persisted.

Local deterministic replay then verified the block-container label flow:
`社区花园节水试验` appeared in the rename dialog and was saved as the group
label. The native revision screen generated `记录频率从每周调整为每天。` and
showed the explicit “由 AI 生成，请核对实际变更后再采用此摘要” notice. The
post content hash and revision list were unchanged by that summary action.

The AI settings page, request-log link, MCP access link and Abilities Explorer
link were inspected in the candidate browser. The installed AI 1.5 feature
list rendered in Chinese, including the connector-owned fixed UI. These checks
are acceptance evidence for the exercised flows, not a claim that every toggle
has been paid-model tested. Image generation/editing, visual alt text, TTS,
type-ahead, WebMCP, MCP execution, and automatic editor updates remain
connector-capability or policy-dependent and were not enabled or executed.

Fixture-scoped quality and journey records were sanitized (pending quality was
empty; observability was unchanged; customer-journey buffer dropped only
fixture-matching records). The temporary MU guard, state file and diagnostic
were removed. No production data, credentials, approvals, Cloud source or
scene registry was changed.

Evidence files: `wordpress-ai-15-integration-evidence-2026-11-17/` under the
Codex visualization root, including native rewrite, saved editor and revision
summary screenshots, the real-call receipts, and pre/post content snapshots.
