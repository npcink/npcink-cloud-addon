# Customer Journey Transport v1

## Purpose

The Cloud Addon records a minimal customer journey signal so pre-user and
invited-user acceptance failures can be diagnosed without asking non-technical
users for precise technical reports.

This transport is enabled only when verified Cloud settings have explicit
WordPress-local monitoring consent. It reuses the existing hourly observability
Cron hook and creates no scheduler, analytics dashboard, audit history, or
product-control truth.

## Closed Contract

The Addon emits `customer_journey_event.v1` only for the supported WordPress AI
editor journeys: title generation, summary generation, rewrite, and save. The
supported steps are `started`, `succeeded`, `failed`, `retried`, `accepted`,
and `abandoned`.

Allowed event fields are limited to:

- opaque event and rotating session identifiers;
- an optional operator-declared opaque cohort id;
- fixed surface, journey, and step values;
- UTC occurrence time and bounded duration;
- optional Cloud run id;
- bounded error category and machine error code.

Events must never include prompt text, source or generated content, post id,
user id, email, URL, DOM state, arbitrary exception messages, credentials, or
headers. Post and actor context are used only in a local keyed hash that rotates
every 30 minutes; raw values are not retained in the journey buffer.

For a bounded invited-user observation window, the local operator may set
`NPCINK_CLOUD_ADDON_CUSTOMER_JOURNEY_COHORT_ID` in WordPress configuration.
The value must be an opaque 1-64 character identifier containing only letters,
digits, `.`, `_`, `:`, or `-`. It must not contain a person, account, site,
article, or prompt identifier. Invalid values fail closed and are omitted. The
constant is read at capture time, is not copied into an Addon setting, and
should be removed when the observation window closes.

Example Local commands:

```bash
wp config set NPCINK_CLOUD_ADDON_CUSTOMER_JOURNEY_COHORT_ID human-value-20260819
wp config delete NPCINK_CLOUD_ADDON_CUSTOMER_JOURNEY_COHORT_ID
```

## Delivery and Query

The local option buffer is capped at 200 events. Each best-effort flush sends at
most 50 events to `POST /v1/customer-journey/events` using a content-addressed
idempotency key. Failed or uncertain uploads retain the batch; accepted events
alone are removed.

`npcink_cloud_addon_get_customer_journey_summary()` performs a manual signed
read from `GET /v1/customer-journey/summary`. It is deliberately a PHP helper,
not a WordPress analytics page. During early validation, operators inspect the
existing Cloud summary only when investigating a concrete acceptance session.
Passing the same opaque cohort id isolates the declared observation window from
earlier technical smoke events without exposing editor identity.

## Ownership Boundary

Cloud owns metadata ingestion and read-only aggregation. WordPress continues to
own monitoring consent, content, generation adoption, approval, and every final
write. Journey evidence may guide diagnosis but must not trigger automatic
repair, approval, content mutation, user scoring, or behavioral profiling.
