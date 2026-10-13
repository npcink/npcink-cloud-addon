# Admin feedback and Chinese localization audit — 2026-10-13

## Change envelope

Repository: `npcink-cloud-addon`; module: connector administrator feedback.
Audit connection and activation, entitlement reads, Site Knowledge delivery,
monitoring, local permission persistence, and local disconnect. Preserve the
existing recovery changes in the mixed worktree. No Cloud lifecycle, billing,
runtime endpoint, public connector, credential format, consent, or WordPress
content changes. The only response extension is escaped status fragments on
the existing capability/nonce-protected administrator AJAX reads.

Files: existing settings render/actions, localization catalog/fallback, admin
assets, bounded action results, observability pause handling, regression tests,
and verification records. Rollback is selective reversal of this audit's patch;
do not discard the earlier recovery work. No cross-repository matrix is needed.

## Findings and corrections

- The local site's older zh_CN pack lacks 41 relevant fixed messages that also
  lacked fallback coverage. Another 60 messages depended entirely on language
  pack coverage. Fill all 101 fixed-message gaps from the reviewed bundled PO;
  preserve upstream translations and leave brand/currency identifiers intact.
  Add the new zero-change message to both fallback and catalog. A coverage
  regression scans only the six addon-owned administrator/action modules;
  dynamic ability metadata and other text domains are excluded.
- Initial indexing/rebuild delivery scheduling used green success styling.
  Accepted index requests now use informational feedback; zero-change refresh
  explicitly says that no content was sent. Disabled delivery cannot report an
  old queued recovery as currently running.
- Asynchronous reads updated counts while leaving connection warnings and
  knowledge status/recovery controls at their previous snapshot. Return markup
  from the same escaped server renderers and update the corresponding status
  areas, including the overview service label. Independent local failures still
  survive a successful Cloud read.
- Expired knowledge evidence could claim all public content was current. That
  claim and the initial-index action now require current cached evidence.
- The knowledge-only page silently removed waiting counts on read failure.
  Show a translated failure and a retry button only after failure; hide both
  after a successful read. Explicit CSS prevents WordPress button display rules
  from overriding the hidden attribute.
- Successful monitoring opt-out left prior upload attention until a cron flush,
  although opt-out removes that cron schedule. Clear obsolete upload attention
  immediately using the existing pause helper, retaining buffered metadata,
  counters, historical success/failure flags and any current projection error.
- Retry failure could restore the page's original quota/plan after a newer
  successful read. Retain the latest successful value instead.

Connection verification, secure permission-save failures and local disconnect
already distinguish local outcomes from Cloud execution; no additional defect
of this class was established in those paths. Their destructive/consent flows
were not exercised against the actual site during this audit.

## Verification

Passed on this working tree: `composer run test:all`, `composer run stan`,
`composer run check:wporg`, `composer run check:js` (11 tests),
`composer run i18n:check`, `composer run package:release`,
`composer run package:verify`, `composer run plugin-check:release`, and
`git diff --check`.

Browser evidence on 2026-10-13:

- Local MySQL site `https://magick-ai.local`: Chinese knowledge and overview
  views; healthy reads hide the retry control; entitlement refresh clears the
  stale banner and updates the service label to recently reachable.
- Isolated local HTML fixture: uses the actual escaped PHP renderers and shipped
  knowledge controller; network failure shows the Chinese read error and retry,
  then retry success replaces the old warning, headline and recovery controls
  together. Fixture data is synthetic; it is not Cloud completion evidence.
- Screenshots (ignored): `build/admin-feedback-audit-overview-2026-10-13.jpg`
  and `build/admin-feedback-audit-isolated-recovery-2026-10-13.jpg`.

Playground is not applicable: bootstrap, activation, public connector API,
default credential state and WordPress/PHP baseline are unchanged. This is an
addon admin audit, not an audit of other plugins or of Cloud indexing. Local
knowledge delivery's earlier recovery completed, but the live Cloud coverage
still reported 1000 pending items; this audit does not claim indexing complete.
No commit, PR, or release publication was performed.

## Second-round follow-up

The follow-up audit checked every addon-owned Cloud upload response and every
administrator AJAX refresh path for the same stale-success behavior. Two
metadata upload paths accepted a successful HTTP envelope without requiring
Cloud's `accepted_count`: observability and customer-journey delivery. Both
now fail closed, retain their local buffers, and record a translated error;
monitoring state projection follows the same rule. The shipped Chinese MO was
also loaded directly through WordPress's POMO reader to verify the refresh
notice, recovery warning, and these two errors resolve to Chinese when a site
language pack is incomplete.

The only remaining English literals are browser last-resort fallbacks used
when server-provided localized configuration is unavailable; normal WordPress
admin and AJAX rendering supplies translated labels. No additional stale
warning, default-success, or missing fixed-string path was found in the
reviewed addon surfaces.

Second-round verification passed: `composer run test:all`, `composer run
stan`, `composer run check:wporg`, `composer run check:js`, `composer run
i18n:check`, `composer run package:release`, `composer run package:verify`,
`composer run plugin-check:release`, and `git diff --check`. No commit, PR, or
release publication was performed.
