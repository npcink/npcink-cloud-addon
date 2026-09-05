# ADR 003: Media Continuation Handoff And Addon Boundary

## Status

Accepted for the 0.2.0 closeout. The handoff is staged: the old Addon
continuation remains dormant until the Toolbox continuation has passed its
behavior and live-site checks.

## Context

The Addon historically carried media-recognition plan state, page advancement,
retry scheduling, and callback wakeups. That made a connector responsible for
local orchestration and encouraged callers to depend on the concrete runtime
client. The media optimization workflow also needs a stable local cursor and
operator recovery, while Cloud must remain the authority for execution,
entitlements, run windows, run_id, and results.

## Decision

- npcink-cloud-addon is a connector/transport owner only.
- Toolbox is the only local continuation owner. It may own a bounded option,
  id_asc cursor, lock, WP-Cron continuation, retry/backoff, pause reason,
  and progress projection.
- Cloud remains the execution, quota, run, and result authority. The Toolbox
  must not read Addon options or Cloud provider state.
- Addon public media seams are limited to source upload, image
  context/evidence request, run/status/result reads, and verified artifact
  download/validation/ACK.
- Generic verified runtime-client integration, terminal callbacks, generic
  Cloud proxying, local run truth, and WordPress writes are out of scope.
- Migration is a handoff, not an immediate deletion: record a read-only
  snapshot, prove no active old plan, pause the old cron, validate the new
  owner, then remove obsolete state. Keep the snapshot as rollback evidence.

## Rejected Alternatives

- Keeping two writers creates duplicate batches and ambiguous cursors.
- Moving Cloud run state into Toolbox duplicates truth and cannot explain
  provider or entitlement decisions.
- A generic proxy or callback endpoint expands the public attack and
  integration surface without a bounded product contract.
- Deleting old state before checking activity can strand an in-flight run and
  remove the only recovery evidence.

## Consequences

Addon tests should assert endpoint and payload allowlists, redaction, bounded
sizes/timeouts, and absence of orchestration APIs. Cross-repository acceptance
must distinguish candidate, exact-SHA CI, M4 candidate, and production evidence.
The current local paused-plan snapshot is rollback evidence, not a conversion
format.
