# Local WordPress AI Localization Closeout (2026-07-27)

## Outcome

The WordPress AI reply-suggestion controls and generating state now have a
bounded zh_CN compatibility mapping in the Cloud Addon `ai` text-domain shim:

- `Suggest Reply` and `Suggest reply`;
- `Generating AI reply…`;
- `Friendly`, `Professional`, and `Casual`.

The shim is loaded in the document head so its `wp.i18n` locale data is
available before the WordPress AI comment component renders. The durable source
change was merged through PR #65. It does not alter Cloud requests, provider
selection, comment data, or WordPress write ownership.

## Incident Pattern

The initial source check was correct but the visible Local site still showed
English. The cause was not a missing mapping: the site was mounted from a
different Git worktree than the terminal checkout. That mounted worktree also
predated the reply-suggestion mappings and loaded the shim in the footer.

This is a local acceptance topology issue. It is not a production release
finding and must not be generalized into a new Cloud or WordPress control
plane.

## Decisions

1. Fixed AI-plugin UI strings may be covered by the bounded `ai` shim. Dynamic
   abilities, schemas, provider/model identifiers, prompts, and slugs remain
   owned by their registering source and are not bulk-translated here.
2. Browser evidence starts by resolving the mounted plugin worktree, not by
   trusting the current terminal directory.
3. A JavaScript localization change requires three separate checks:
   - deterministic source-map behavior;
   - emitted locale-data and load-order behavior;
   - rendered browser confirmation on the target admin screen.
4. A Local acceptance worktree may carry a minimal temporary verification fix,
   but the same reviewed change must reach a clean branch based on current
   `master` before any worktree is removed.

## Reusable Workflow

Before local browser acceptance, run:

```sh
composer run local:runtime -- /absolute/path/to/wordpress
```

Record the site URL, resolved plugin target, branch, short SHA, and worktree
status alongside the browser result. The command is read-only; it neither
changes a Local symlink nor writes WordPress data.

For a source change, run the applicable deterministic checks, then prepare a
clean topic branch from current `origin/master`. Do not create a PR from an old
feature branch merely because it contains the desired commit: a clean
cherry-pick avoids accidentally publishing historical branch changes or being
blocked by an outdated merge base.

## Development Lessons

- Tests can prove a translation dictionary and still miss a wrong running
  worktree or an after-render script load. Treat source, runtime enqueue, and
  browser rendering as distinct evidence.
- Local worktree isolation is useful for parallel acceptance, but its binding
  to a site is operational state that must be surfaced explicitly.
- Keep observability and quality follow-ups metadata-only. The adjacent
  editor-assist quality correlation work reuses the opt-in bounded
  observability buffer; it must never turn a generated suggestion or a later
  save into automated approval, rejection, prompt tuning, or a WordPress write.
- Closing a change means preserving a clean upstream source first. Removing
  temporary worktrees is a later housekeeping step, after the merged commit is
  independently recoverable.

## Verification Record

The localization closeout used:

```sh
composer validate --no-check-publish
composer run test:all
composer run check:wporg
git diff --check
```

The release result and Local browser observation remain separate evidence. A
successful CI check or an HTTP response alone is not a claim of browser or
production acceptance.
