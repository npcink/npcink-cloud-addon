<!--
Keep the Scope, Boundary, Verification, and Risk headings below.
The PR body contract requires them before merge.
-->

## Scope

- [ ] This change is limited to the stated Cloud Addon module.
- [ ] Public PHP seam, runtime client contract, Site Knowledge bridge, observability bridge, or product boundary docs were updated if changed.
- [ ] No unrelated generated files, local environment files, or cross-repo worktree changes are included.

## Cloud Addon Boundary

- [ ] Addon remains a thin Cloud connector.
- [ ] This does not add router, prompt, preset, approval, proposal, workflow/task queue, scheduler truth, workflow engine, billing truth, or WordPress write ownership.
- [ ] This does not reintroduce `/v1/runtime/workflows/runs`.
- [ ] Stored secrets are not printed, logged, split into unsafe UI fields, or returned through REST responses.
- [ ] Site Knowledge delivery remains bounded public content-change delivery; Cloud owns index lifecycle and freshness policy.

## Verification

- [ ] `composer validate --no-check-publish`
- [ ] `composer test:all`
- [ ] `composer run smoke:playground` when the change affects plugin bootstrap, activation, public connector API, default credential/connector state, or the WordPress/PHP baseline; otherwise record why it is not applicable.
- [ ] `composer check:wporg`
- [ ] Boundary search for `/v1/runtime/workflows/runs|wp_insert_post|wp_update_post` if PHP runtime or bridge files changed.

## Risk

- Residual risk:
- Rollback plan:

## Release Impact

- [ ] No release impact.
- [ ] Requires package/release verification.

## Notes

Summarize the behavior change, boundary decision, and known follow-up.
