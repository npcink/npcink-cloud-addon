# Site Knowledge recovery follow-up — 2026-10-13

The user reported an English success notice with zero sent items and an
unchanged warning for 11 dropped content updates after clicking Update again.
The installed Chinese language pack lacked the fixed notice; the bundled PO
already translated it, but the runtime fallback did not. The recovery action
only buffered recent public content, so older dropped updates were not
recovered and the completed full-delivery condition never cleared the warning.

Dropped-content recovery now reuses the existing administrator `start`
delivery intent to send all public posts and pages in bounded batches. It does
not delete or rebuild the index, and Cloud retains index execution and lifecycle
ownership. The admin result shape stays unchanged; scheduled and already-active
recovery results use informational notices. Repeated requests preserve active
delivery instead of starting another request or reporting zero items as success.

Queued or healthy ongoing recovery replaces the historical dropped-content
warning with Updating and hides duplicate recovery actions. The dropped fact
remains in advanced diagnostics until the existing final-batch completion clears
it. Retrying, blocked delivery, or site scheduling failures retain attention.
Independent stale connection evidence still uses Check connection. Fixed notice
and service-health strings have Chinese fallbacks for lagging language packs,
and the bundled POT, PO, and MO are refreshed.

Verification on 2026-10-13:

- Tier A: `composer run test:all`, `composer run stan`,
  `composer run check:wporg`, `composer run i18n:check`, and `git diff --check`
  passed. Recovery coverage includes old public content across multiple batches,
  duplicate clicks, scheduling failure, final-batch clearing, ongoing/blocked
  UI, Chinese fallback, informational notice styling, and independent stale
  connection evidence.
- Package and strict Plugin Check passed after rebuilding:
  `composer run package:release`, `composer run package:verify`, and
  `composer run plugin-check:release`.
- Tier C: the real Local MySQL site `https://magick-ai.local` mounts this
  worktree. Browser submission returned a Chinese scheduled-recovery notice,
  removed the historical page-level warning, and displayed Updating. The
  bridge selected 1,998 public items; a later snapshot recorded 1,200 delivered
  and ongoing delivery, with the 11 historical drops still retained. This is
  progress evidence, not a completed Cloud indexing claim. An active repeat
  request returned the Chinese already-in-progress notice without restarting.
  The notice rendered as `notice-info`. A separate connection check refreshed
  expired evidence successfully; its warning used the connection action.
  The informational notice screenshot is retained in ignored
  `build/site-knowledge-recovery-2026-10-13.jpg`. At the final check, all 1,998
  public items had been delivered, the maintenance cursor was idle, the local
  buffer was empty, and the dropped count had automatically cleared from 11 to
  zero. The browser no longer showed the historical warning or recovery button.
  The cached Cloud projection still reported 1,000 items waiting, so no claim
  is made that Cloud indexing is complete. The final screenshot is retained in
  `build/site-knowledge-recovery-completed-2026-10-13.jpg`.
- Tier B is not applicable: no bootstrap, activation, public connector API,
  default credentials, or WordPress/PHP baseline changed. The cross-repository
  matrix is not applicable: no cross-repository contract changed.

At the time of the 2026-10-13 evidence collection, the changes were uncommitted
on `codex/041-refresh-service-status` and no PR or deployment had been performed.
The recovery and subsequent refresh fixes are now merged in PR #271 at
`master@6db6638`; see the [2026-10-14 final release record](041-final-release-record-2026-10-14.md)
for current release status. WordPress.org publication remains pending. Rollback
is reversal of the scoped PR; do not discard unrelated worktree changes or clear
live delivery facts to mimic success.
