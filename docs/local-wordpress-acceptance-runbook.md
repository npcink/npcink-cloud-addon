# Local WordPress Acceptance Runbook

## Purpose

Local WordPress browser evidence applies to the plugin worktree mounted by the
site, not necessarily to the directory from which a command was run. This
runbook prevents a verified code change from being mistaken for a verified
running-site change.

This is a local acceptance procedure. It does not change Cloud contracts,
WordPress write ownership, or release evidence requirements.

## Required Runtime Check

Before browser acceptance, report the mounted plugin worktree:

```sh
composer run local:runtime -- /absolute/path/to/wordpress
```

The command is read-only. It returns JSON with the WordPress root, plugin link,
resolved plugin target, symlink status, Git branch, short SHA, and worktree
status. `WP_PATH` may be supplied instead of the positional path.

The acceptance record must include:

- Local site URL.
- Resolved plugin target.
- Git branch and short SHA.
- Whether the mounted worktree was clean or dirty.

Do not infer the running source from the terminal working directory.

## Worktree Safety

- Do not repoint a Local site's plugin symlink to a worktree with unrelated
  uncommitted changes merely to test one fix.
- Keep a named, clean acceptance worktree for an isolated feature when a Local
  site needs to exercise it.
- When the mounted acceptance worktree needs a browser-only fix, make the
  smallest change there, then carry the same reviewed change into its intended
  source branch before release.
- A local acceptance worktree is not production and does not establish a
  release or GA claim.

## JavaScript Localization Acceptance

For a fixed `ai` text-domain UI translation, deterministic PHP tests only prove
the shim map. Browser acceptance must also prove the runtime path:

1. Run the required runtime check above.
2. Confirm the target source strings are present in the locale data served by
   the mounted plugin.
3. Confirm the locale shim executes before the AI component renders. In this
   addon the shim must be loaded in the document head, not the footer.
4. Refresh the target admin screen and confirm the visible controls use the
   expected Chinese text.

Use the bounded compatibility shim only for fixed AI plugin UI copy. Do not add
dynamic ability metadata, schemas, provider IDs, model IDs, prompts, or slugs.

## Verification Record

For a localization change, record separately:

- `composer run test:all` result;
- `composer run check:wporg` result;
- `git diff --check` result;
- mounted-worktree output from `composer run local:runtime`;
- manual browser observation, including the exact admin screen.

These are separate evidence tiers. A passing unit test or HTTP response does
not replace a rendered-browser confirmation.
