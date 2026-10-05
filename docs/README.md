# Documentation Index

Navigation for `docs/`. Two conventions keep this directory findable:

- New dated documents (closeouts, retrospectives, session notes) are added to
  this index under **History** in the same change that adds them.
- The **History** list is archival evidence: it explains why the addon looks
  the way it does, but the **Standards** and **Contracts** entries are the
  living rules. When a dated document states a rule that later changed, the
  standard or ADR wins.

## Start Here (required by AGENTS.md)

- `cloud-addon-boundary.md` — what the addon owns and must never own.
- `cloud-addon-complexity-budget.md` — which complexity may stay, the size
  ratchet, and where tests belong. Read before expanding addon scope.
- `admin-surface-standard.md` and
  `admin-simplification-and-delivery-engineering-standard-2026-08-25.md` —
  read both before changing the settings page or any operator surface.

## Standards

- `admin-surface-standard.md` — settings-page and operator-surface rules.
- `admin-simplification-and-delivery-engineering-standard-2026-08-25.md` —
  quiet automatic behavior, recovery surfaced only after real failures.
- `runtime-seam-closeout-and-engineering-standard-2026-08-13.md` — runtime
  seam rules (dated document kept as a standard reference).
- `ux-history-and-engineering-standard-2026-08-13.md` — user-facing error
  and UX surfacing rules.
- `wordpress-ai-acceptance-standard-v1.md` — WordPress AI acceptance gates.
- `ai-plugin-localization-maintenance.md` — zh_CN compatibility-string rules.
- `public-cloud-onboarding-checklist.md` — public onboarding prerequisites.

## Contracts

- `cloud-runtime-client-contract.md` — signed transport client contract.
- `ai-task-runtime-contract-v1.md` — AI task runtime payload contract.
- `customer-journey-transport-v1.md` — customer-journey event transport.
- `editor-assist-quality-correlation-v1.md` — editor assist quality seam.
- `site-knowledge-vector-operations.md` — Site Knowledge vector operations.
- `site-knowledge-full-index-delivery.md` — full-index delivery flow.
- `site-knowledge-recommendation-connector-record-v1.md` — recommendation
  connector record.
- `adapter-integration-seam.md` — Adapter channel integration seam.
- `cloud-bulk-article-run-seam.md` — bulk article run seam.
- `cloud-site-connection-flow-history.md` — connection flow evolution.

## Guides

- `local-test-guide.md` — local verification workflows, including the
  `scripts/wp-cli-local.sh` environment resolution order.
- `local-wordpress-acceptance-runbook.md` — Local MySQL/Cloud acceptance.
- `media-continuation-migration-runbook.md` — media continuation migration.
- `wordpress-org-release-gate.md` — WordPress.org release gate.
- `wordpress-org-release-and-translation-log.md` — release/translation log.
- `wordpress-ai-compatibility-stability-ledger-v1.md` — compatibility
  stability ledger.
- `local-business-validation-plan-v1.md` and
  `local-business-validation-evidence-2026-09-20.md` — business validation.

## Decisions

- `decisions/001-remove-concrete-runtime-client-seam.md`
- `decisions/002-production-monitoring-consent-and-package-handoff.md`
- `decisions/003-media-continuation-handoff-and-addon-boundary.md`

## History (dated closeouts, retrospectives, handoffs)

- `security-performance-closeout-2026-06-27.md`
- `runtime-boundary-pr16-closeout-2026-07-01.md`
- `public-cloud-readiness-closeout-2026-07-02.md`
- `cloud-addon-admin-ui-simplification-2026-07-02.md`
- `cloud-addon-admin-surface-closeout-2026-07-03.md`
- `cloud-addon-diagnostics-closeout-2026-06-30.md`
- `site-knowledge-index-management-closeout-2026-06-30.md`
- `cloud-addon-reference-notes-2026-07.md`
- `cloud-addon-contract-reuse-readiness-2026-07-08.md`
- `connect-ui-and-zh-cn-localization-closeout-2026-07-09.md`
- `manual-readiness-result-closeout-2026-07-09.md`
- `admin-overview-and-release-retrospective-2026-07-13.md`
- `local-wordpress-ai-localization-closeout-2026-07-27.md`
- `cloud-site-capacity-and-cross-repo-release-retrospective-2026-08-08.md`
- `development-experience-and-methodology-2026-08-08.md`
- `next-session-handoff-2026-08-08.md`
- `production-monitoring-development-retrospective-2026-08-19.md`
- `wordpress-ai-acceptance-and-release-handoff-2026-09-08.md`
- `wordpress-ai-request-log-compatibility-2026-09-08.md`
- `observability-producer-handoff-2026-09-11.md`
- `admin-ui-cloudflare-alignment-retrospective-2026-09-22.md`
- `local-security-gate-and-ai-i18n-session-notes-2026-09-22.md`
- `test-suite-vacuity-retrospective-2026-09-23.md`
- `image-context-evidence-integration-summary.md`
- `user-facing-error-surfacing-closeout-2026-10-02.md`
- `static-analysis-and-local-tooling-closeout-2026-10-04.md`
- `branch-and-dependabot-maintenance-closeout-2026-10-05.md`
