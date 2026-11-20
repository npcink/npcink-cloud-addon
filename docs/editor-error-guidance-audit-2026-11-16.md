# Editor and Addon failure guidance audit — 2026-11-16

Scope: Cloud Addon editor notice navigation and existing settings error actions.
The active WordPress AI plugin is 1.5.0. This source audit is not an exhaustive
browser audit of every WordPress, Toolbox, or Cloud screen. No runtime dispatch,
provider, credential, permission, content, billing or retry behavior changes.

| Surface | Finding and treatment | Evidence |
| --- | --- | --- |
| Editor authorization, disconnected and inactive-site errors | Add a new-tab link to Checks and its existing connection-check/recovery actions; keep the original reason. A cached verification is not overwritten by a browser hint. | PHP routing contract; representative authorization notice click in Edge. |
| Editor credits exhausted and general Cloud limit | Add a direct plans/credits link to `/portal/billing`; limits retain their wait instruction and do not imply that buying credits fixes every limit. | PHP mapping, simultaneous-notice JS test, real billing page reached. |
| Editor service unreachable/unavailable and unusable output/image contract/verification/ack results | Add troubleshooting-copy navigation alongside existing retry advice. | Six exact-message mappings; representative unreachable notice in browser; earlier copy landing and current copy flow. |
| Editor generic runtime failure | Retain the preceding explicit path, copy action and new-tab draft protection. | Existing tests retained and expanded. |
| Nonadministrator connection/account errors | Explain who can resolve the issue; no inaccessible settings/account link. | PHP capability config and JS idempotent handoff tests; not a real-role browser acceptance. |
| Checks failure | Contact support previously opened Portal home. It now opens a new site-related ticket form with an adjacent copy-information anchor and concrete paste/submit instructions. | Browser opened the selected-site dialog; copy showed success; no submission. |
| Dropped knowledge updates | Replace obsolete Sync wording with the actual Update knowledge base button label; remove redundant self-anchor. If delivery is off, explicitly enable Site Knowledge first rather than pointing at an absent button. | Enabled/disabled dropped-state PHP regression; not a new live-failure browser fixture. |
| Knowledge capacity, overdue updates, delivery failures, account read failure | Existing Cloud detail, Checks/delivery detail, recovery and read-retry actions are already present. Retain them. | Source review and existing render/JS contracts; not fresh browser coverage of all states. |
| Generation test dialog | Failure guidance already has a bounded Ability Explorer link; field/session/network failures say what to do next. Retain them. | Source review of `admin-generation-tests.js` and dialog rendering. |
| Input, paused/unsupported feature, in-progress and rate-limit errors | Input or wait instructions are local steps, not missing navigation. Do not add automatic retries or re-enable unsupported features. | Exact matching excludes unrelated notices; existing pause/runtime tests retained. |

Information hierarchy: current error and next action remain visible; navigation
opens existing details on demand; raw diagnostic evidence stays in Technical
details/copy output; redundant self-navigation is removed. No new card, tab,
drawer, control plane or diagnostic truth is introduced.

The native notice enhancer preserves identity, dismissal, existing actions and
unrelated messages, does not repeat live announcements, and adds each family’s
link only once. Building the localized configuration must not call the public
error formatter (it records the last error), mutate that evidence or invoke AI.
The new helper is loaded only on `post.php` and `post-new.php`; it does not change
bootstrap, activation, public transport, credentials or compatibility floors,
so Playground bootstrap testing is not applicable to this change.

Local deterministic gates: `test:all`, PHPStan, JavaScript, i18n freshness,
WordPress.org checks, package build/verification and strict Plugin Check. The
independent upstream AI 1.5 global translation audit still has the previously
recorded 180 gaps; this work does not claim to fix that separate audit.

Browser evidence: task evidence directory
`01a1e282-815d-7332-b08c-71217440e6c6`, `guidance-audit-receipt.json`,
`guidance-editor-1440.jpg`, `guidance-connection-target.jpg`,
`guidance-checks-1440.jpg`, `guidance-support-destination.jpg` and additional
PC screenshots. Error notices and the unavailable readiness response are
explicit local display fixtures; no provider call or actual outage was made.
The browser loaded the source Addon using a temporary request-scoped selector;
the default `wp-ai-15-connector` candidate checkout/cookie is preserved. The
fixture was removed after verification. Browser evidence proves the source
navigation, not integration into that candidate or a real generation recovery.

The initial editor viewport was measured at 1440×1050. A requested 1024×900
override did not remain applied: later DOM measurements were 1912×918 and
1912×914, so narrow-PC verification remains unmeasured. Screenshots retain
their captured dimensions; do not infer width from filenames alone. No measured
horizontal overflow; copy target visible. The editor retains existing upstream
preload/deprecation/iframe-style warnings; Checks had no observed errors or
warnings. Complete network failure capture is unmeasured.

Source and local evidence only. CI, merge, M4 candidate/accepted, production
deployment and human visual acceptance are separate and not completed here.

At closeout the earlier candidate symlink and selector were no longer present
in the local environment. This task did not remove them. A fresh ordinary
editor URL (no fixture query) loaded the source Addon asset and its twelve-rule
configuration with all three translated action labels; see
`guidance-live-source.jpg`. CLI reflection independently resolved the connector
to `/Users/muze/gitee/npcink-cloud-addon`. Thus the earlier candidate caveat
describes the fixture phase, not the final ordinary local-page loading state.
No new real generation failure or recovery was manufactured for this check.

## Follow-up: connection success versus title input failure — 2026-11-17

The earlier navigation audit did not cover all local validation families or the
success notice emitted by the detailed-check handler. Its generic runtime rule
therefore still sent oversized input to support, while that handler said
“Everything is working” after a liveness probe and signed entitlement read.
This was a coverage gap: browser navigation success did not prove either input
classification or actual generation success.

Real local evidence: the two title attempts at 09:21:19 and 09:21:34 China time
failed with `cloud_wp_ai_connector_request_too_large`, duration 0 ms and no Cloud
run. The article measured 26,000 raw bytes / 19,342 raw characters versus 11,321
bytes / 4,663 characters after stripping tags. Editor formatting and the
Ability prompt count toward the existing 24,000 encoded-byte request boundary
and 12,000-character source boundary. These are local limits; no provider or
connection outage is inferred. Exact prompt lengths were not retained.

Treatment: map the five text/image request/source/prompt size codes to shorter
input / smaller sections and the system-instruction size code to shorter
custom instructions. Keep raw codes for support, preserve provider/commercial
classification and all input limits, and do not truncate or strip the host
Ability prompt. Rename the detailed-check action and its success message to
connection/account access; explicitly state that generation is not tested.
Connection checking must not erase an editor failure or imply it has recovered.

Information layers: scope and actionable input failure stay visible; existing
troubleshooting disclosure stays on demand; exact dated error remains technical
evidence; the unsupported all-health success claim is removed. No new options,
HTTP paths, retries, control-plane responsibilities or content writes.

Local gates passed: `test:all`, PHPStan, i18n freshness and WordPress.org guard.
The added regression follows oversized Chinese title input through the actual
client guard and message classification with zero HTTP, plus six size-code
families and visible Checks scope. Existing liveness/read tests retain the
historical AI failure. Playground is not applicable: no bootstrap, activation,
public transport, credential defaults or compatibility baseline changes.

Real browser: ordinary local editor title retry shows the new size-specific
message. The existing detailed-check action succeeds and shows its bounded
success message. A temporary local runtime-POST blocker was present to prevent
accidental provider execution; it was never invoked and was removed. No input
or result fixture was used. The stored title/body/excerpt/status/modified-time
fingerprint was identical before and after. Editor Save became enabled after
title focus/retry; no Save was clicked, so only persisted-content preservation
is claimed, not an unchanged in-memory dirty state.

Evidence: task directory `01a1e282-815d-7332-b08c-71217440e6c6`,
`check-scope-receipt-20261117.json`, `check-scope-editor-20261117.jpg`,
`check-scope-result-20261117.jpg`; actual viewport 1912×914, no horizontal
overflow. Existing missing featured-image, upstream translation gaps,
preload/deprecated-slot/iframe-style warnings remain. Console title errors
match the intentionally reproduced real input failure; a Checks view-transition
abort was captured from before verification. Complete network capture and
narrow-PC verification are unmeasured. Generation recovery, CI, merge, M4 and
production remain unverified; this change fixes explanation/classification,
not the article's ability to pass the existing input limit.
