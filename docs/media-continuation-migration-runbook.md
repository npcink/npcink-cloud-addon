# Media Continuation Migration Runbook

This runbook is for the one-time handoff from the historical Addon owner to
Toolbox. It is not a recurring migration mechanism.

## Preconditions

Record, for Addon, Toolbox, and Cloud:

- exact HEAD SHA, branch, and git status --short --branch;
- mounted WordPress site and plugin target;
- current Cloud candidate SHA and CI state.

Read the four legacy values on the target site:

npcink_cloud_addon_media_recognition_plan,
npcink_cloud_addon_media_recognition_plan_lock,
npcink_cloud_addon_media_index_status, and the
npcink_cloud_addon_continue_media_recognition cron event.

Do not continue when a plan is active, processing, or eligible for retry.
Capture a read-only JSON snapshot including plan_id, state, cursor,
current_run_id, counts, retry metadata, and pause reason.

## Handoff

1. Pause the old plan with an explicit migration reason.
2. Remove or disable its cron wakeup without deleting the snapshot.
3. Mount the candidate Toolbox and verify one writer, stable id_asc ordering,
   cursor commit after a successful batch, pending-run handling, bounded retry,
   pause after the failure limit, explicit recovery, and stale-lock recovery.
4. Run the real media path: upload, Cloud v3 run, qualified/skipped,
   artifact byte/image validation, delivery ACK, continuation progress, pause,
   and recovery.
5. Only after the previous checks pass, clear obsolete Addon option, lock,
   transient, and progress fields through the release cleanup path.

## Rollback

Rollback means restoring the recorded source SHAs and the paused-plan snapshot
through the owning repository's reviewed procedure. Do not mix an old Addon
writer with a new Toolbox writer, and do not delete a Cloud run directly from
the database. A Cloud run that is queued but not visible through the API is an
operations issue requiring the Cloud path, not local database surgery.

## Evidence

The release record must contain the three SHAs, snapshot location, active-plan
decision, cron result, exact test commands, live endpoint result, package
SHA-256, and any M4 limitation. A code test is not evidence of cache hit rate,
provider request count, scan duration, or failure rate; collect those only
during an explicitly bounded observation window.
