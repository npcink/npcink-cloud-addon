# Editor Assist Quality Correlation v1

## Scope

The Cloud Addon silently correlates successful WordPress AI text suggestions
with later explicit local saves or publishes. It tracks:

- `ai/title-generation` as `title_generation`;
- `ai/summarization` as `content_summary`;
- `ai/content-resizing` as `content_rewrite`.

The official WordPress AI plugin continues to own the editor interaction. This
addon does not patch its JavaScript and does not add feedback controls.

## Consent and Storage

Correlation runs only when verified Cloud monitoring is enabled.

The temporary local option
`npcink_cloud_addon_editor_assist_pending` is non-autoloaded, capped at 100
records, and has a one-hour session TTL. It may contain the local post ID and
current actor ID only for matching. It retains no prompt, source content, or
generated text.

Generated output is reduced to an HMAC-SHA256 fingerprint using the local
WordPress auth salt. Cloud-bound object and actor scopes are separate keyed
HMACs. Disconnect and uninstall remove the local pending option.

## Save Classification

`wp_after_insert_post` observes the saved main post. Revisions, autosaves, and
`DOING_AUTOSAVE` writes are ignored.

- `saved_exact_output`: the HMAC of the saved title or a saved body/block text
  equals one pending output fingerprint. Confidence is `high`.
- `saved_after_generation_unmatched`: an explicit save occurred, but no exact
  fingerprint matched. Confidence is `medium`; this can mean editing or no
  adoption and must not be labelled rejection.
- `expired_without_save`: no matching explicit save was seen in one hour.
  Confidence is `medium`.

The existing hourly observability hook expires stale sessions before uploading
the buffered batch. No new cron schedule is created.

## Boundary

- WordPress save/publish is the human approval and final-write truth.
- Governance Core is not invoked by this editor-assist path.
- The Addon never writes generated content to a post.
- Cloud receives only fields allowlisted by the existing plugin observability
  collector.
- Cloud issue candidates may request an offline evaluation, but cannot change
  production prompts, models, routing, or workflows.

## Verification

Run:

```bash
composer test:all
```

The focused behavior test is:

```bash
php tests/behavior-editor-assist-quality.php
```
