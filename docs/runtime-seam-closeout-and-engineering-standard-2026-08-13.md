# Runtime Seam Closeout and Engineering Standard (2026-08-13)

Status: active engineering retrospective and operating standard.

Scope: the work that migrated maintained consumers away from the concrete
runtime client, removed `npcink_cloud_addon_runtime_client()`, stabilized the
required WordPress Playground gate, verified the release candidate, and merged
the result. This document records process and reasoning; it does not expand the
addon boundary or authorize a production release.

## 1. Outcome

The workstream ended with the following state:

- addon connection, credential replacement, health UX, and Site Knowledge
  fixes merged in PR #88;
- addon-owned runtime consumers migrated to scenario facades in PR #89;
- the remaining external Eval Lab concrete-client consumer migrated in Eval
  Lab PR #56;
- maintained Toolbox and Adapter authoritative branches contained no concrete
  client calls;
- `npcink_cloud_addon_runtime_client()` was removed in PR #90;
- addon facade construction moved behind an undocumented internal factory;
- Playground activation and default fail-closed verification passed on the
  supported WordPress 7.0.4 / PHP 8.2 baseline;
- deterministic tests, boundary searches, PHP 8.0/8.2/8.4 CI, release static
  gates, package construction, package manifest verification, and strict
  Plugin Check passed;
- no production tag, GitHub Release, WordPress.org release, or production
  deployment was created.

The final distinction is intentional:

```text
code verified != PR merged != candidate package verified != production released
```

Each state requires separate evidence and authority.

## 2. Problem Evolution

The initial issue looked like a small API cleanup: a deprecated global helper
exposed `Npcink_Cloud_Runtime_Client`. A systematic review showed that removal
was actually a dependency-order problem:

1. identify all consumers;
2. add or confirm bounded replacement facades;
3. migrate addon-owned and sibling consumers;
4. verify authoritative remote branches are clean;
5. remove the seam and its compatibility assertions;
6. prove a fresh WordPress activation still works;
7. build and verify the exact package;
8. publish through protected-branch workflow.

Removing the function before steps 1–4 would have converted architectural
cleanup into a cross-repository break. Keeping it after those steps would have
preserved accidental coupling for no user benefit.

## 3. Principal Engineering Decisions

### 3.1 Prefer scene contracts over concrete clients

A concrete client gives callers access to every public method it accumulates.
A scene facade exposes one fixed intent, validates its contract, and preserves
ownership. The latter makes the correct behavior easy and prevents sibling
plugins from turning the addon into a generic signed proxy.

Rule: sibling repositories call `npcink_cloud_addon_*` scenario facades. They
do not construct, request, or retain `Npcink_Cloud_Runtime_Client` instances.

### 3.2 Remove accidental APIs during the pre-user window

Compatibility has a cost. In a pre-user project, preserving an unwanted seam
can be riskier than removing it because every additional caller makes later
removal harder.

Rule: use the pre-user phase to narrow interfaces, but first prove the absence
of external users and migrate every maintained authoritative consumer. The
formal conditions are in
[ADR-001](decisions/001-remove-concrete-runtime-client-seam.md).

### 3.3 Internal naming is not access control

Replacing a public global function with another global function whose name
contains `internal` does not create a real boundary. The implementation uses a
class that is loaded for addon-owned facade construction but omitted from the
documented public interface.

Rule: do not solve an interface leak with naming alone. Control discovery,
documentation, call sites, and regression tests together.

### 3.4 Preserve truth ownership while refactoring transport

The refactor changed only how the addon obtains its signed client. It did not
move approval, proposal, workflow, scheduler, billing, Site Knowledge lifecycle,
or WordPress write truth.

Rule: every transport refactor starts with the question “transport/detail or
control/write truth?” If the answer is control/write truth, stop and move the
change to the appropriate owner.

## 4. Consumer Inventory Standard

Before deleting or changing an observable API, build a consumer matrix:

| Surface | Required evidence |
| --- | --- |
| Current repository | source, scripts, tests, docs, package manifest |
| Sibling repositories | authoritative remote default branches |
| Local worktrees | identify stale branches separately from canonical truth |
| Generated artifacts | package ZIP and manifest |
| Runtime fixtures | Playground/MU-plugin or equivalent smoke |

Search local worktrees and remote branches separately. A local topic branch may
contain an old call after `origin/master` has migrated. Report it as a stale
branch compatibility issue, not as an unresolved canonical consumer. Do not
reset or rewrite that unrelated worktree to make the scan look clean.

Minimum evidence for this seam:

```bash
rg -n 'npcink_cloud_addon_runtime_client\s*\(' .
git show origin/master:<consumer-file> | rg 'npcink_cloud_addon_runtime_client'
```

The exact files vary by repository; the important rule is to inspect the
authoritative branch rather than infer its state from a possibly old checkout.

## 5. Playground Failure Investigation

### 5.1 Original symptom

`composer run smoke:playground` stopped before WordPress boot with only:

```text
Error: fetch failed
```

Retrying the same opaque operation did not establish whether the failure came
from npm, WordPress version discovery, the WordPress ZIP, the SQLite integration,
or plugin activation.

### 5.2 Investigation sequence

The useful sequence was:

1. read `scripts/smoke-playground.sh` and the Blueprint;
2. inspect the pinned CLI help and installed package implementation;
3. identify that a version such as `latest` or `6.9` still triggers the
   WordPress version API before downloading the archive;
4. pass an exact WordPress ZIP URL to bypass version discovery;
5. prefetch that archive with `curl` retries and verify it with `unzip -t`;
6. place it under the cache name the CLI derives from the URL;
7. rerun and observe the next real failure;
8. align the archive with the plugin header requirement (`Requires at least:
   7.0`), selecting WordPress 7.0.4;
9. run the smoke repeatedly to confirm cache reuse and activation stability.

The first deterministic run exposed a previously hidden fact: WordPress 6.9.5
could download correctly but could not activate a plugin requiring WordPress
7.0. Fixing the network symptom without reading the activation result would
have left the gate invalid.

### 5.3 Adopted Playground rules

- Pin the CLI, PHP version, and an exact supported WordPress archive.
- Prefetch network artifacts with bounded retries and integrity checks.
- Reuse the runtime's persistent cache instead of creating a repository-owned
  binary cache.
- Classify failures before plugin boot as environment failures, but do not
  convert them to skips or passes.
- Preserve the server log and surface plugin activation errors.
- Test the default fail-closed state and the intended public API, not merely an
  HTTP 200 response.
- Run the smoke more than once when changing cache behavior.

## 6. Verification Ladder

Use evidence in increasing order of cost and realism.

### Tier 1: deterministic source contracts

```bash
composer run test:all
git diff --check
rg '/v1/runtime/workflows/runs|\b(?:wp_insert_post|wp_update_post|wp_insert_attachment|wp_update_attachment_metadata|update_post_meta|wp_set_post_terms|set_post_thumbnail|media_handle_sideload)\s*\(' \
  --glob '*.php' --glob '!build/**' .
```

This proves syntax, behavior contracts, boundary text, zero-write posture, and
forbidden-route absence. It does not prove WordPress activation.

### Tier 2: disposable WordPress compatibility

```bash
composer run smoke:playground
```

This proves activation and default connector posture in SQLite/WASM WordPress.
It does not prove MySQL, signed Cloud transport, Portal, browser UX, or
production networking.

### Tier 3: candidate package verification

```bash
composer run release:verify
```

This proves the exact manifest, ZIP contents, localization freshness, static
release gates, and Plugin Check result. Inspect the ZIP when adding a source
file so a passing source test cannot hide an incomplete package manifest.

### Tier 4: protected-branch evidence

Publish with the repository template and `composer pr:publish`. Wait for PHP
matrix, PR body, aggregate contracts, and release static gates. The merge
commit—not the local topic commit—is the authoritative completion point.

### Tier 5: release or deployment

Tags, GitHub Releases, WordPress.org publication, and production deployment are
operator-authorized activities. They are not implied by green tests, a merged
PR, or a verified ZIP.

## 7. Standard Change Envelope

Start consequential addon work with a compact written envelope:

- target repositories;
- focused module;
- intended behavior change;
- explicit non-goals;
- public contracts touched;
- expected files;
- files and areas that must not change;
- required local gates;
- whether a cross-repository matrix is required;
- rollback plan.

For this workstream the non-goals were as important as the goal: no router,
prompt, preset, approval, proposal, queue, scheduler, billing, WordPress write,
Cloud deployment, tag, or Release changes.

## 8. Implementation and Publication Checklist

### Before editing

1. Run `git status --short --branch`.
2. Fetch the remote default branch.
3. Create `codex/<topic>` from `origin/master`, never from a prior topic.
4. Read `AGENTS.md`, the complexity budget, and affected contracts.
5. Write the change envelope.

### Before deleting an interface

1. Classify the interface as intended contract or leaked implementation.
2. Confirm product/user status.
3. Inventory addon-owned and sibling consumers.
4. Migrate authoritative consumers first.
5. Add regression tests for the replacement.
6. Define a source-only rollback.

### Before staging

1. Run required tests and the applicable Playground gate.
2. Inspect `git status --short --branch` and `git diff --stat`.
3. Run `git diff --check`.
4. Stage explicit paths; never use `git add -A` in a mixed worktree.
5. Check `git diff --cached --stat` and `git diff --cached --name-only`.

### Before publishing

1. Commit with a description that records both what changed and why.
2. Confirm the worktree is clean.
3. Complete `.github/pull_request_template.md` outside the repository.
4. Include Scope, Boundary, Verification, Risk, residual risk, and rollback.
5. Run `composer pr:publish -- --title ... --body-file ...`.
6. Wait for all required checks and auto-merge.
7. Fetch `origin/master` and record the merge commit.

### After merge

1. Do not equate merge with production release.
2. Keep branch/worktree cleanup separate and read-before-delete.
3. Record any stale local consumer branches without rewriting them.
4. Update long-lived docs when the decision would otherwise be rediscovered.

## 9. Common Failure Modes

### Removing first and searching later

Why it fails: cross-repository callers break before a replacement is ready.

Correction: inventory and migrate first; delete last.

### Treating local checkout state as remote truth

Why it fails: old worktrees create false positives, while un-fetched remote
changes create false negatives.

Correction: report local and authoritative remote state separately.

### Replacing one leaked global with another

Why it fails: naming does not prevent external calls.

Correction: use an internal implementation unit, omit it from public docs, and
test the documentation boundary.

### Retrying an opaque environment failure

Why it fails: repeated `fetch failed` messages provide no new evidence.

Correction: split version discovery, archive download, integrity, boot, and
activation into observable stages.

### Pinning an unsupported compatibility baseline

Why it fails: a deterministic download can still deterministically test the
wrong WordPress version.

Correction: derive the smoke baseline from the plugin header and supported
compatibility policy.

### Calling a verified package a release

Why it fails: technical readiness does not grant operational authority.

Correction: use precise state language—candidate verified, PR merged, tag
created, release published, or production deployed.

### Cleaning unrelated branches or stashes during closeout

Why it fails: shared worktrees may contain user-owned or parallel work.

Correction: preserve unrelated state and make cleanup a separate authorized
operation.

## 10. Boundary Result

The Cloud boundary remained intact throughout this work:

- local truth stayed local;
- Cloud remained runtime/detail only;
- the addon remained credentials plus bounded signed transport;
- no generic Cloud proxy was added;
- no forbidden infrastructure or control-plane surface was introduced;
- no secret was printed or logged;
- no WordPress write ownership moved into the addon.

The refactor reduced coupling without changing product ownership.

## 11. Historical References

- Addon PR #88: connection, credential replacement, health UX, and Site
  Knowledge closeout.
- Addon PR #89: addon-owned runtime consumer migration to facades.
- Eval Lab PR #56: external concrete-client consumer migration.
- Addon PR #90: concrete runtime client seam removal and Playground gate
  stabilization.
- Addon PR #90 merge commit: `3e72a107ed1f86261b5939669a9ef0b6b7b387d0`.
- [ADR-001](decisions/001-remove-concrete-runtime-client-seam.md).
- [Development experience and methodology](development-experience-and-methodology-2026-08-08.md).
- [Local test guide](local-test-guide.md).
- [Adapter integration interface](adapter-integration-seam.md).
- [Cloud Addon complexity budget](cloud-addon-complexity-budget.md).
- Platform PR standard:
  `/Users/muze/gitee/npcink-workflow-toolbox/docs/platform/pr-publishing-standard-v1.md`.
