## Scope

- [x] Improve local permission switch feedback, focus restoration, and no-JavaScript fallback.
- [x] Improve Site Knowledge mobile action hierarchy.
- [x] Complete fixed WordPress AI zh_CN compatibility strings and refresh POT/PO/MO.
- [x] Add `docs/ux-history-and-engineering-standard-2026-08-13.md` covering historical UX findings, engineering method, boundaries, and verification.

## Cloud Addon Boundary

- [x] Addon remains a thin Cloud connector.
- [x] No router, prompt, preset, approval, proposal, workflow/task queue, scheduler truth, billing truth, or WordPress write ownership was added.
- [x] `/v1/runtime/workflows/runs` was not reintroduced.
- [x] Stored secrets remain out of UI, logs, and responses.
- [x] Site Knowledge remains bounded public delivery; Cloud owns index lifecycle and freshness policy.

## Verification

- [x] `composer run test:all`
- [x] `composer run check:js`
- [x] `composer run check:wporg`
- [x] `composer run i18n:check`
- [x] `composer run ai:i18n:audit` (fixed UI candidates: 0; remaining items are dynamic metadata/schema/prompt categories)
- [x] `composer run smoke:playground`
- [x] `git diff --check`
- [x] Forbidden endpoint and WordPress write API boundary scan

## Risk

- Residual risk: Real logged-in browser interaction and Cloud Portal/MFA/revocation flows still require an environment with an authenticated local WordPress session and real Cloud state.
- Rollback plan: Revert this commit; no database migration or irreversible Cloud-side change is included.

## Release Impact

- [x] Requires package/release verification because admin assets and generated language files changed.

## Notes

The permission switch now locks during submission, gives row-level feedback after redirect, and restores focus. Site Knowledge keeps one primary mobile action. Fixed AI plugin UI localization is complete without translating dynamic ability metadata. The new engineering standard records the historical problem set and the reusable facts-first workflow for future Cloud Addon work.
