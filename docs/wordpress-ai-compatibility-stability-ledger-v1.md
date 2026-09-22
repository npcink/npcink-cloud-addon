# WordPress AI Compatibility Stability Ledger v1

Status: active evidence ledger. Date: 2026-09-22.

## Purpose

This ledger accumulates decidable evidence for one pending operator decision:
whether the `WordPress AI compatibility blocking lanes` aggregation job
(`wordpress-ai-compatibility-required` in `.github/workflows/ci.yml`) should
become a branch-protection required context. The job is currently not
required. Two observed WordPress.org download 403 failures (rate limiting
that exhausted the former short retry burst) showed that third-party
flakiness, not an addon regression, was the blocking failure mode.

The operator approved three stability-evidence actions on 2026-09-22, all
inside this repository and with no branch-protection change:

1. `scripts/smoke-wordpress-ai-compatibility.sh` now spreads archive download
   retries over minutes (`--retry 8 --retry-delay 20 --retry-max-time 900`)
   instead of the former roughly 15-second burst.
2. `.github/workflows/ci.yml` runs the same matrix nightly on master HEAD
   (19:37 UTC) through a `schedule` trigger. The scheduled run behaves like a
   master push; the PR-only `pr-body-contract` workflow does not listen for
   `schedule` events and is not affected.
3. This ledger records the evidence.

## Counting Rules

Two ledgers with different questions. Never mix their samples.

- Ledger A (product gate streak) answers: is the blocking lane green for
  every merged master revision on the first attempt?
  - Population: complete CI runs triggered by a merge into `master`.
  - Only the first-round result counts. Any first-round failure, whether code
    or third-party, resets the streak to zero.
  - A rerun that turns green is recorded as a jitter event. It is neither a
    passing sample nor does it restore the streak.
  - Scheduled nightly runs never enter ledger A, even though they run the
    same matrix on master HEAD.
- Ledger B (environment stability) answers: how often does the third party
  (WordPress.org downloads, runner infrastructure) fail this repository?
  - Population: all runs, including PR lanes and scheduled nightly runs.
  - Track the third-party failure rate over the observation window.
  - Scheduled runs are environment-stability evidence only. They are never
    product evidence.
- A run cancelled by concurrency (`cancel-in-progress` shares one group
  between master pushes and the nightly schedule) has no first-round result.
  Record it as an `infra` observation in ledger B, and require a rerun for a
  cancelled master run before ledger A records it.

## Row Fields

Every observation row records: date, revision SHA, PR number, trigger
(`push` for master merges, `pull_request`, or `schedule`), lane coverage,
first-round result, failure class (`code` / `third-party-download` / `infra`),
whether a rerun happened, and recovery time (first-round failure to a green
rerun; `not applicable` when no rerun was needed, `not measured` when
unknown). Classify failures from first-round evidence, not from the rerun.

## Upgrade Threshold

Submit the operator decision to add `WordPress AI compatibility blocking
lanes` to required contexts only when ledger A reaches a streak of 10 or more
consecutive first-round green master runs and the ledger B third-party
failure rate is approximately zero across the same window. The operator, not
this ledger, makes and applies the branch-protection decision.

## Ledger A: Master First-Round Results

| Date | SHA | PR | Trigger | Lane coverage | First round | Failure class | Rerun | Recovery |
| --- | --- | --- | --- | --- | --- | --- | --- | --- |
| 2026-09-18 | fb18e05 | #150 | push (master) | full CI; failed Release static gates | fail | code (fixed by #151) | no | not applicable |
| 2026-09-18 | f9ec318 | #151 | push (master) | full CI | pass | - | no | not applicable |
| 2026-09-20 | d8317e0 | #152 | push (master) | full CI; WordPress AI stable-primary / PHP 8.0 failed | fail: curl 403 downloading blocking WordPress AI 1.3.0 | third-party-download | no; re-proven by #153 | not applicable |
| 2026-09-20 | 7797cdd | #153 | push (master) | full CI | pass | - | no | not applicable |
| 2026-09-20 | 078bf41 | #154 | push (master) | full CI | pass | - | no | not applicable |

Current ledger A streak: 2 (#153, #154). The streak was reset on 2026-09-20
by the PR #152 third-party download failure.

## Ledger B: Known Jitter and Third-Party Events

| Date | Run | Event | Class | Outcome |
| --- | --- | --- | --- | --- |
| 2026-09-20 | PR #154 lane run | first-round 403 download failure | third-party-download | rerun passed |
| 2026-09-20 | PR #152 master run | curl 403 downloading blocking WordPress AI 1.3.0 (stable-primary / PHP 8.0) | third-party-download | no rerun; re-proven by #153 |

From 2026-09-22, exhausted retries are stronger evidence of a real outage
window because the retry spread now covers roughly three minutes. Record
nightly scheduled runs here (trigger `schedule`) as environment-stability
evidence only.

## Integrity

This ledger is filled in manually by task sessions or audits from real CI
records. Do not manufacture runs to grow samples, do not synthesize rows
without a real run, and do not count a rerun as a first-round pass. Branch
protection remains untouched while evidence accumulates.
