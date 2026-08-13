# Cloud Addon × Cloud Runtime: Development Experience and Methodology (2026-08-08)

Status: active methodology note distilled from the 2026-08-08 cross-repo
workstream (site capacity rework + production release). It records not what
happened (see the retrospective documents) but the reusable way of thinking
that made each phase correct and fast.

Scope: engineering methodology for single-session development across
`npcink-cloud-addon` and `npcink-ai-cloud`. It does not expand addon scope,
Cloud product ownership, or any boundary in `cloud-addon-boundary.md`.

## 1. Diagnose from Production Data Before Theorizing

When a symptom looks contradictory ("portal shows no sites, yet binding is
rejected as over the limit"), resist the urge to patch the symptom.

- Read the deployed code path that produces the error code
  (`service.site_limit_exceeded`) to learn the exact decision rule.
- Read the production data that the rule consumes: SSH to the Cloud host,
  read the runtime config (`/opt/npcink-ai-cloud/shared/config/
  runtime-config.json`), and query the database read-only (accounts, sites,
  bindings, subscriptions, snapshots).
- Name the two views that disagree. Here: portal visibility joins
  `principal_site_bindings`, while capacity accounting counts `sites` by
  `account_id`. The fix must address the *contract between views*, not
  either view alone.

Method: symptom → code rule → data → conflicting-view analysis → fix at the
contract level. Keep the inspection path reusable (SSH → config → SQL) so
the next diagnosis takes minutes, not an exploration.

## 2. Model State Transitions, Not Flag Checks

"An account may bind many sites; only `site_limit` may be active at once"
is a state-machine statement, not a single capacity check.

- **Bind** (create/reconnect/relink) and **activate** (provisioning →
  active) are different transitions. Capacity that gates activation must be
  checked at the activation transition, not at bind time.
- A "soft ceiling" for bound sites (`max(3, site_limit * 3)`) is a
  separate policy from the activation quota; do not overload one counter.
- Count only the states that the rule means: activation counts `active`
  only; provisioning/suspended are not active.
- Reconnect of a released site creates a new binding: run the bind check
  whenever the existing site has no active binding, not only on
  cross-account relink.

## 3. Count-Then-Mutate Needs a Lock

Any "count X then decide then mutate" inside a transaction can race: two
concurrent requests both read the same count, both pass, both mutate. For
per-account limits, acquire the account row lock (`SELECT ... FOR UPDATE`)
before reading the mutable state, and re-read after acquiring it so a
duplicate concurrent request is treated as an idempotent update instead of a
spurious limit violation. Re-entrant locks within one transaction are
harmless; assert on the lock *set*, not the lock *count*, in tests.

## 4. Contract and Security Layers Are Fail-Closed and Interlocked

This platform runs a two-layer CVE gate: the image allowlist
(`deploy/image-lock/cve-allowlist.json`) is validated by an exact-set
supply-contract test and by the first-install CVE gate script's governed
set. Changing one layer without the others fails CI by design.

- Read the contract tests before touching the data file; they encode the
  exact allowed set and the reason templates.
- Grant a temporary exception only with a reachability assessment (the
  HTTP/2 CVEs were unreachable because the Next.js standalone server sits
  behind nginx TLS termination on HTTP/1.1) and an expiration date.
- Treat every automated review (codex-connector) as a pre-merge gate:
  resolve its threads, because unresolved threads block auto-merge even
  when all checks are green.

## 5. Make the Process Deterministic, Then Measure It

The cost of this release was dominated by process, not by the code:

- **Branches** always come from `origin/master` (`git switch -c <topic>
  origin/master`); never from the current branch.
- **PRs** go through the repository publisher (`scripts/publish-pr.sh`,
  `composer pr:publish`): it enforces a clean worktree, an up-to-date base,
  the body contract, idempotency, and requests squash auto-merge.
- **Testing** is change-scoped per a fixed mapping (production code changes
  map to their focused suites); the full suite runs only at the release
  gate.
- **Release timing** is recorded node-by-node (a timing log) so every
  release ends with a data-backed optimization list instead of a vague "it
  was slow".

The deterministic rules live in
`npcink-ai-cloud/docs/single-session-ai-workflow-standard-v1.md`; the
timing data and optimization follow-ups live in
`npcink-ai-cloud/docs/cloud-site-capacity-production-release-retrospective-2026-08-08.md`.

## 6. Reusable Checklist

1. Symptom → read the code rule → read production data → name the two
   disagreeing views → fix the contract.
2. For capacity changes: separate bind vs activate; count only the states
   the rule means; lock the account row before count-then-mutate.
3. For allowlist/contract changes: update data + exact-set contract test +
   governed gate set together; run `pytest tests/contract/`.
4. For every PR: branch from `origin/master`, use the publisher script,
   resolve review threads, wait for CI, squash-merge.
5. Before a release: promote after all fixes are batched, wait for the
   `production` push CI, approve the environment immediately, record timing
   nodes, and write the retrospective with optimization follow-ups.

## 7. Cross-References

- This addon's release/retrospective:
  `docs/cloud-site-capacity-and-cross-repo-release-retrospective-2026-08-08.md`.
- Cloud release retrospective:
  `npcink-ai-cloud/docs/cloud-site-capacity-production-release-retrospective-2026-08-08.md`.
- Single-session workflow standard:
  `npcink-ai-cloud/docs/single-session-ai-workflow-standard-v1.md`.
- This addon's boundary and release gates:
  `docs/cloud-addon-boundary.md`, `docs/wordpress-org-release-gate.md`.
- Runtime public-seam removal and Playground gate standard:
  `docs/runtime-seam-closeout-and-engineering-standard-2026-08-13.md` and
  `docs/decisions/001-remove-concrete-runtime-client-seam.md`.
