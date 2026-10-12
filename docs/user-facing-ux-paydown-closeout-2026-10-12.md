# User-facing UX paydown closeout — 2026-10-12

Status: PR 1 merged as [#263](https://github.com/npcink/npcink-cloud-addon/pull/263).
This document records the addon-owned A-class UX and localization cleanup from
the 2026-10-12 handoff. It does not close the GlotPress queue, B-class UX
items, or Cloud/runtime contract work.

## Delivered behavior

- Removed the unused Site Knowledge coverage anchor, JavaScript query, and
  dead state branch while preserving quota refresh behavior.
- Added explicit disconnect confirmation for both JavaScript and no-JavaScript
  paths. The no-JavaScript path presents a warning and required checkbox, and
  the server rejects destructive requests without either confirmation marker.
- Localized missing model metadata errors and all 22 AI task-contract
  validation messages through the addon text domain.
- Classified structurally valid expired image artifacts as `artifact_expired`;
  malformed timestamps and envelopes remain `artifact_contract_invalid`.
- Selected the Chinese or English privacy terms URL from the active locale.

## Boundary decisions

The change adds no REST endpoints, public PHP facade, Cloud contract,
persistent field, router/prompt/preset ownership, scheduler truth, billing
truth, or WordPress write authority. Endpoint allowlists, credential handling,
Site Knowledge delivery ownership, and Cloud index lifecycle remain unchanged.

GlotPress stable/dev and readme translations were subsequently completed in a
separate operational step; see the [2026-10-12 translation closeout](wordpress-org-release-and-translation-log.md#2026-10-12-zh_cn-glotpress-closeout)
for the live counts and scope limits. The default provider acceptance batch
also subsequently passed 14/14; see the [provider acceptance rerun](wordpress-org-release-and-translation-log.md#2026-10-12-provider-acceptance-rerun)
for the environment, run IDs, and skipped optional lanes. B-class admin UX
items, image artifact continuation, and capability stale-while-revalidate
remain open and are not represented as completed here.

## Verification evidence

- `composer run test:all` — passed, including boundary search.
- `composer run stan` — passed.
- `composer check:wporg` — passed.
- `composer run check:js` — passed.
- `composer i18n:check` — passed.
- `composer run package:release` and `composer run package:verify` — passed.
- `composer run plugin-check:release` — strict result passed.
- `git diff --check` — passed.
- Local wp-admin browser check on `https://magick-ai.local` with the mounted
  worktree at `codex/ux-tail-20261012`: zh_CN overview rendered; Site Knowledge
  recovery state rendered; connection management showed the JavaScript confirm
  handler and the no-JavaScript warning plus required confirmation checkbox.
  The local Cloud service was unavailable, so existing recovery messaging was
  expected.
- `ocr review --from origin/master --to HEAD` was attempted after the final
  PR 1 commit. The configured review provider returned HTTP 429 quota
  exhaustion for all requests, so no second-opinion findings were available.

Playground smoke was not applicable: bootstrap, activation, default connector
state, public API, and the supported WordPress/PHP compatibility baseline were
unchanged.

## Rollback

Revert PR #263 (`e55a04e` before squash) or the merged commit. No data
migration or Cloud contract rollback is required.
