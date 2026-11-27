# Cloud Addon admin visual audit follow-up

Date: 2026-11-27. Scope: the hosted Cloud Addon visual audit, not the separate
[20-item engineering audit](cloud-addon-audit-closeout-2026-11-26.md).
This record captures the local candidate at `aeebe0c`; it is not evidence of a
merge, release, deployment, or live Cloud acceptance.

## Disposition

| Finding | Disposition |
| --- | --- |
| Advanced connection address became excessively tall on narrow screens | Fixed in `8b42cbd`: reset the desktop flex basis in the column layout. Checked the distinct unconnected advanced entry and connected manual fallback. |
| Activation notice crowded on Dashboard and Plugins | Fixed in `8b42cbd`: title, description, and actions use separate rows with native WordPress styles. No expansion of Addon stylesheet loading or dismissal scope. |
| Site Knowledge usage failure exposed a diagnostic code and icon-only retry | Fixed in `aeebe0c`: one labeled read retry, separate collapsed diagnostic code, and explicit stale-value labeling. Recovery does not send content or change the Cloud index. |
| Monitoring prompt claimed to be modal without modal behavior | Fixed in `aeebe0c`: native modal and backdrop, focus on decline, Escape uses the existing decline action, and explicit forms work without JavaScript. Ask-once and opt-in remain intact. |
| Date presentation, missing AI-plugin prompt weight, status contrast | Deferred. Time is already rendered in the site timezone; format changes should not alter credit-period semantics or delivery date logic. Measure contrast before changing it. |
| Parent Overview tab selected on exclusion subpage; healthy layout | Keep the parent context and back link. Preserve the quiet three-tab layout and period-used figure. |

## Verification and limits

- Disposable WordPress Playground (WordPress 7.0.4, PHP 8.2) with the candidate
  source mounted. Chromium checked setup and notices at 390, 782, 783, and
  1440 px; it also checked the Chinese notice and per-user dismissal.
- The authenticated WordPress AJAX handler produced the usage-read failure
  with Cloud HTTP blocked. A browser-injected successful response verified the
  display recovery; it does not prove a signed Cloud round trip. The prompt
  was checked at 390 and 1440 px, including keyboard focus, Escape, reminder
  suppression through the reconnect eligibility check, and explicit opt-in
  through the existing form with JavaScript disabled.
- Local gates passed: `test:all`, `check:js` (41 tests), `stan`, `i18n:check`,
  `check:wporg`, `smoke:playground`, `package:release`, and `package:verify`.
  This does not establish the full GitHub check rollup. Official WordPress AI
  plugin editor and generation dialog, real Cloud usage, and production remain
  outside this evidence. No change to the Cloud compatibility contract.

## Lessons to reuse

The first connected-page browser check only found a matching address field;
it did not prove the connected state. The fixture had written a legacy flat
credential shape, and its cache seed initially used an unnormalized URL. The
final run used the normal settings writer, the normalized cache scope, and an
explicit verified-state assertion before measuring the correct control.
Record that distinction rather than treating all screenshots as equivalent.

The enduring behavior and validation rules live in the
[admin surface standard](admin-surface-standard.md) and
[verification evidence standard](verification-evidence-standard.md). After
GitHub integration, verify the exact PR head, complete check rollup and merged
content before removing only the task-owned branch and worktree.
