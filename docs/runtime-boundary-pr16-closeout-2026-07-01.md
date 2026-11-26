# Runtime Boundary PR 16 Closeout

Status: accepted.

Date: 2026-07-01.

## Context

PR #16 started as an adversarial project review follow-up for
`npcink-cloud-addon`. The review target was not to add another product surface.
The target was to make the addon safer to merge by checking project
positioning, ownership boundaries, security, performance, runtime contracts, and
WordPress AI plugin localization compatibility.

The final scope was intentionally narrow:

- keep Cloud Addon as the Cloud connector;
- harden read/runtime endpoint boundaries;
- keep Pro Cloud Runtime detail read-only and Cloud-owned;
- update the central quality matrix path from
  a checkout of the former `npcink-toolbox` repository to
  a checkout of `npcink-workflow-toolbox`;
- expand the WordPress AI plugin zh_CN compatibility shim only for fixed admin
  UI strings.

## Boundary Decision

Cloud Addon remains a connector and local status/detail surface.

It must not become:

- a router, prompt registry, preset registry, or provider control plane;
- an approval/proposal/workflow/task queue owner;
- scheduler truth, billing truth, retry queue truth, or workflow engine;
- a WordPress final-write owner;
- a second Site Knowledge lifecycle, freshness, or collection-management
  control plane;
- a full upstream WordPress AI plugin language pack.

Runtime/read access remains limited to named endpoints. The forbidden
`/v1/runtime/workflows/runs` surface must not return. Direct WordPress write
calls such as `wp_insert_post` and `wp_update_post` must stay absent from addon
runtime paths.

## Implemented Shape

The merged PR hardened three areas.

First, Pro Cloud Runtime projection now treats omitted optional fields as
unavailable instead of silently rendering them as `0`. Boolean-like Cloud fields
are parsed strictly so false-like strings such as `"false"` do not render as
exhausted. The settings page renders runtime feature, quota, batch limit,
retention, and quota exhaustion only from available Cloud entitlement detail.

Second, Cloud Addon docs and tests now use the central matrix workspace at
a checkout of `npcink-workflow-toolbox`. The addon keeps its own release
gates local, while cross-repo closeout uses the shared matrix from Workflow
Toolbox.

Third, the WordPress AI plugin localization shim was expanded for fixed admin UI
families:

- request log labels and detail fields;
- connector approval notices and matrix copy;
- AI home save/reset/model labels;
- image generation, image editor, and media controls;
- comment moderation columns, filters, and statuses;
- dashboard status and capability labels;
- editor feature actions;
- Abilities Explorer fixed navigation and controls.

The shim remains static, admin-only, Chinese-locale-only, and limited to text
domain `ai`. It must not call Cloud runtime and must not translate dynamic
ability metadata, JSON/schema keys, provider/model identifiers, user content,
prompts, or long generated instruction templates.

## AI i18n Audit Result

The final audit result after the merged work was:

```text
Discovered ai-domain strings: 645
Shim translations: 431
Missing fixed UI candidates: 254
Possibly stale shim strings: 40
```

The remaining 254 candidates were intentionally not treated as a simple backlog.
They include DataViews/core-ish strings, long image prompt templates, Abilities
Explorer prose, dynamic ability schema/descriptions, REST schema descriptions,
and backend permission/failure strings. Those are not addon-owned fixed UI
strings unless a later product decision says otherwise.

## PR and Git Closeout

PR #16 was marked ready only after local gates and the cross-repo matrix passed.
Before merge, GitHub's PR body contract failed because the body did not include
the required `Scope`, `Boundary`, `Verification`, and `Risk` headings. The PR
body was updated to satisfy that contract and the GitHub checks passed.

GitHub HTTPS push/fetch transport was unreliable during the work. Some branch
updates were applied through the GitHub Git Data API after normal `git push`
failed. The PR was then squash-merged through GitHub:

- PR: <https://github.com/muze-page/npcink-cloud-addon/pull/16>
- Merge commit: `a2991bd047eedc228b1c4a0dd484f914cf27de73`
- Merge subject: `Harden Cloud Addon runtime boundaries`

After merge, local `master` was aligned to `origin/master`. A temporary backup
branch `codex/local-master-before-pr16-sync` was created before the alignment
and later deleted after the merged state was verified.

## Verification

Addon-local gates passed after merge:

```bash
composer run test:all
git diff --check
sh -c '! rg "/v1/runtime/workflows/runs|wp_insert_post|wp_update_post" --glob "*.php" --glob "!build/**" .'
composer run ai:i18n:audit
```

Cross-repo closeout also passed from
a checkout of `npcink-workflow-toolbox`:

```bash
composer quality:matrix:run
```

The matrix passed all repo gates, including:

- `npcink-abilities-toolkit`
- `npcink-governance-core`
- `npcink-ai-client-adapter`
- `npcink-workflow-toolbox`
- `npcink-cloud-addon`
- `npcink-ai-cloud`
- `magick-ai-toolbox`

## Follow-Up Rule

Do not chase the remaining AI i18n audit candidates by default. Add more shim
strings only when they are confirmed fixed admin UI from text domain `ai`.

Do not add generic runtime proxies, workflow runs, local retry queues, local
billing/quota truth, Site Knowledge lifecycle controls, or WordPress write
ownership to close future audit or diagnostics gaps. If a new Cloud detail is
needed, add a named endpoint contract, document the boundary, and add tests
before rendering it in the addon.
