# Cloud Site Capacity Rework and Cross-Repo Release Retrospective (2026-08-08)

Status: dated engineering retrospective. Describes why the addon's
"Add this site to Npcink Cloud" flow reported `service.site_limit_exceeded`
even when the portal showed no connected sites, what was changed in the Cloud
runtime (`npcink-ai-cloud`) and in this addon, and the process lessons that
became the single-session workflow standard.

Scope note: this document is an engineering record only. It does not expand
addon scope, product boundaries, or Cloud ownership. The Cloud runtime
semantics described here are owned by `npcink-ai-cloud`; this addon remains a
thin Cloud connector.

## 1. Symptom and First Diagnosis

- The addon connect button opened
  `https://cloud.npc.ink/portal/sites?connect=wordpress-addon&...`, which
  showed "没有此页面" (404).
- After the Portal workspace merged into `/portal`
  (`npcink-ai-cloud` `9c3de78e`, 2026-07-16), the standalone
  `/portal/sites` route no longer existed; unauthenticated visitors were
  307-redirected to login first, so the 404 only appeared after login.
- Fix (this addon, v0.1.4, PR #76): point the authorization URL and the
  "Open Cloud" actions at `/portal`, keeping all
  `connect=wordpress-addon` query parameters.

## 2. Root Cause: Two Visibility/Accounting Views Disagreed

Binding www.npc.ink failed with `service.site_limit_exceeded` even though the
portal listed no connected sites. Production data inspection (SSH to the
Cloud host, read the runtime config, query PostgreSQL) showed:

- Account `acct_magick_ai_local` has a Free subscription (`site_limit = 1`).
- `sites` contains `site_magick-ai-local` (status `active`) bound to that
  account, with an active API key and 53 run records.
- `principal_site_bindings` contains **zero** rows for the portal principal,
  so `list_sites_for_principal` (portal-visible sites) returned nothing.

Two different views:
- **Portal visibility** joins `principal_site_bindings` (principal → site).
- **Capacity accounting** (`count_sites_by_account`) counts `sites` rows by
  `account_id` only.

The site existed and consumed the Free slot, but had no principal binding, so
it was invisible in the portal yet counted against `site_limit`.

Data fix: inserted the missing `principal_site_bindings` row so the site is
visible in the portal.

## 3. Design Intent vs Implementation

Intent: "an account may bind multiple sites; only as many sites as the
configured site limit may be active at the same time."

The implementation checked capacity at **binding** time and counted
`active/provisioning/suspended` against `site_limit`, so binding the second
site was rejected before activation could even be considered. A
single-active-site sibling-deactivation switch made the behavior "one active
site, silently switch on bind".

Reworked semantics (merged as `npcink-ai-cloud` PR #577, follow-ups #579):

- **Activation capacity**: `_assert_account_site_capacity` /
  `_assert_default_free_site_capacity` count **only `active`** sites.
- **Bind soft ceiling**: new `_assert_account_site_bind_capacity` limits
  bound (non-archived) sites to `max(3, site_limit * 3)`.
- **Bind paths** (`create/complete_wordpress_addon_connection`,
  `provision_site`) use the bind soft ceiling; binding is no longer gated by
  the activation `site_limit`.
- **Activation paths** (exchange completion, `issue_site_key(...)`,
  `activate_site`, `provision_site(status="active")`) reject with
  `service.site_limit_exceeded` when the account already has `site_limit`
  active sites; the sibling-deactivation switch was removed.
- **Concurrency**: activation capacity checks serialize per account with
  `get_account_for_update` (row lock) so two concurrent activations cannot
  both pass the count and exceed the limit.
- **Quota reporting**: `bound_sites` reports the bind soft ceiling and a new
  `active_sites` metric reports used/limit against the activation limit;
  the portal quota surfaces `active_sites` so an account at its activation
  cap reports `limited`, not `ok`.

## 4. Release History in This Workstream

- This addon: v0.1.3 → v0.1.4 (PR #76 portal URL, #77 `.distignore`,
  #78 PCP i18n literal). Package + Plugin Check verified locally
  (`Success: Checks complete. No errors found.`).
- `npcink-ai-cloud`:
  - #577 site capacity semantics rework;
  - #578 pre-existing dev test assertion fix surfaced by the release-gate
    full suite (`usage_meter.totals` now always carries zero-value
    accounting cost fields);
  - #579 activation bypasses and quota alignment (codex review findings);
  - #580 single-session workflow standard + CI test mapping.

## 5. Engineering Lessons and Ideas

1. **Inspect production data before theorizing.** The 404 and the
   `site_limit_exceeded` were both proven by reading the deployed code and
   querying the production database, not by guessing. Keep a reusable
   production inspection path (SSH → runtime config → SQL) instead of
   rediscovering it each time.
2. **Two views of the same entity must agree or the contract must say which
   is canonical.** Portal visibility (principal bindings) and capacity
   accounting (`sites.account_id`) disagreed; the fix made capacity follow
   the account view and made the portal binding explicit.
3. **Bind vs activate are different state transitions.** Capacity that
   gates activation should not be checked at bind time; check each state
   transition at its own boundary.
4. **Counters checked inside a transaction need a lock.** Any
   "count then mutate" capacity check must serialize per subject
   (row lock `FOR UPDATE`) or concurrent requests can overshoot the limit.
5. **Change-scoped testing beats full-suite-on-every-edit.** CI maps
   changed production paths to their focused suites; the full suite runs at
   the release gate. A stale assertion surfaced only by the full suite is a
   sign the focused suites need to cover that surface.
6. **Deterministic publication beats manual PR rituals.** Use the
   repository's publisher script (branch up-to-date check, body contract,
   idempotency, auto-merge) instead of hand-rolled `gh pr create` / merge
   sequences. Branch protection (strict up-to-date, conversation
   resolution) must be read as facts, not learned by trial.
7. **Automated review is a gate, not a surprise.** The codex review found
   real P1 issues (concurrency, missing `app/api` test mapping) before
   merge; treat it as an early gate and resolve threads, because unresolved
   threads block auto-merge.
8. **Branches come from `origin/master`, never from the current branch.**
   Wrong-base branches cost rebase + forced-push + full CI re-runs.

## 6. Where the Rules Live

- Single-session workflow standard (branch rule, PR publication,
  change-scoped testing, pre-merge checklist, traps):
  `npcink-ai-cloud/docs/single-session-ai-workflow-standard-v1.md`.
- This addon's boundary and release gates:
  `docs/cloud-addon-boundary.md`, `docs/wordpress-org-release-gate.md`.
- Cloud onboarding checklist: `docs/public-cloud-onboarding-checklist.md`.

## 7. Production Release Outcome (2026-08-08)

The reworked capacity semantics were deployed to `cloud.npc.ink` on
2026-08-08 after an operator-approved promotion (`master` → `production`,
promote PRs #581 and #584) and `deploy-production.yml`. Deployment verified:
`/health/live` 200, `/portal` redirects to login for unauthenticated users.

Release path and gates encountered, in order:

1. Promote PR #581 (site capacity rework + workflow standard) — codex
   review surfaced 3 P2 edge cases (same-account reconnect bypassing the
   bind ceiling, unsynchronized bind-capacity checks, quota accounting
   discrepancy — the third was a false positive); fixed in master PR #582.
2. First deployment attempt — blocked by the frontend-image CVE scan gate:
   two node 22.23.1 HTTP/2 findings (`CVE-2026-56846`, `CVE-2026-56848`).
   Assessed unreachable (Next.js standalone behind nginx TLS termination,
   HTTP/1.1 only), granted operator-authorized temporary exceptions in the
   allowlist with expiration 2026-08-11; the change required synchronizing
   the fail-closed supply contract test and the first-install CVE gate
   governed set (master PR #583, promoted via #584).
3. Second deployment attempt — failed immediately on the "require
   successful CI for this production commit" gate because the `production`
   push-event CI had not completed yet; re-triggered after it went green.
4. Third deployment attempt — success; production health verified.

This addon v0.1.4 remains packaged and PCP verified; the WordPress.org
release (SVN) is a separate operator action.

## 8. Release Timing Data and Efficiency Lessons

Elapsed 14:11 → 17:00 (~2h50m) for the production release. Dominant cost
drivers (from the node-by-node timing log):

| Driver | Time | Optimization |
| --- | --- | --- |
| Full CI re-runs (4 rounds; `production` forces full `backend-pytest`) | ~65 min | Batch all fixes before the first promote; change-scoped CI for `master` PRs already applies |
| Codex review rework (4 reviews, all real: 3 P2 + 1 P1) | ~85 min | Run the same review rules as a local pre-merge check for capacity/concurrency/contract/security changes; surface findings at the `master` PR stage, not at promote |
| CVE gate governance (allowlist + contract + gate script, 2 layers fail-closed) | ~51 min | Document the allowlist↔contract↔gate-script sync as a checklist; assess reachability before granting |
| Environment approval + deploy↔CI timing | ~25 min | Approve the `production` environment immediately after triggering; make the deploy workflow wait for the push-event CI instead of failing |

Process rule adopted from this release: treat every automated review
(codex-connector) as a pre-merge gate on `master`, keep the branch based on
`origin/master`, and use `scripts/publish-pr.sh` for every PR including
promotions.
