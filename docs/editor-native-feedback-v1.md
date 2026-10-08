# Editor Native Feedback v1

Status: implemented connector producer; natural operator acceptance remains a
separate trial step.

## Ownership and meaning

WordPress observes an existing main-post save. When a tracked title, summary
or rewrite exactly matches a locally retained generated-output fingerprint,
Addon queues `cloud_agent_feedback.v1` with `local_outcome=accepted` for the
original `source_run_id` and opaque generation `handoff_id`. Cloud stores the
native `agent.feedback` event through its existing signed endpoint.

This is exact-output adoption evidence. It does not prove writing quality,
operator satisfaction or authorization for a write. Addon performs no post
write. Unknown edits, revisions, autosaves and expired unsaved suggestions do
not become adopted, rejected or subjectively scored native feedback.

## Consent, privacy and delivery

- Capture and delivery require verified settings and existing monitoring opt-in.
- The save observer buffers metadata and performs no network request.
- Payloads contain run/generation association, task kind, the exact-match reason
  and timestamp. Text, prompts, post/user IDs, hashes of content, notes and
  ratings are absent.
- Delivery uses the existing observability WP-Cron hook at priority 20 and the
  existing allowlisted `POST /v1/agent-feedback/events` method. No scheduler or
  workflow queue is introduced.
- The local buffer holds at most 100 events, expires delivery after 24 hours
  and sends at most five events per flush. This is disposable delivery state.
- A failed round stops and preserves the original payload/idempotency key for
  the next hourly retry. Successful removal re-reads the buffer so events
  captured in the same request during HTTP are retained. An unchanged queue
  is not rewritten at flush start; pruning still persists expired or invalid
  entries. Concurrent WordPress requests share a best-effort option buffer,
  without an atomic delivery guarantee. Cloud's idempotency contract handles
  duplicate delivery; this is not an exactly-once transport promise.
- All remote failures stop the round, including authorization failures that
  may recover after settings repair. They are not assumed permanent from a
  4xx status alone; expiry limits a blocked head to 24 hours.
- Opt-out discards pending native delivery. Events captured for a different
  site binding are discarded. Disconnect/uninstall removes the buffer.

An idle site may need its existing WordPress cron runner before feedback is
visible in Cloud. The existing manual command is:

```sh
wp cron event run npcink_cloud_addon_flush_observability
```

## Repeatable verification

Deterministic producer tests exercise exact/unknown saves, autosave/revision
exclusion, original-run association, deferred HTTP, stable retry, in-flight
captures, opt-out, expiry, site binding, private-field rejection and cleanup:

```sh
php tests/behavior-editor-assist-quality.php
composer run test:all
composer run stan
composer run check:wporg
```

In an already verified and opted-in Local WordPress, run:

```sh
sh scripts/wp-cli-local.sh scripts/smoke-editor-native-feedback.php
```

That smoke uses real WordPress options/hook/signing code, shadows addon option
writes in memory and intercepts native HTTP; unexpected endpoints fail closed.
It makes zero post writes,
Provider calls or Cloud feedback submissions. Its fixture is controlled test
evidence, never natural operator adoption. WP-CLI `eval-file` scripts must not
declare `strict_types`, and array HTTP fixtures use lowercase response-header
keys to match WordPress's real header lookup.

Before release, pair this with Cloud's focused Agent feedback route tests for
stored-event association, native idempotency and forbidden-authority rejection.
The operator's first real writing/save round trip remains the natural evidence
anchor. Do not backfill old plugin observations into native accepted feedback.
