# Cloud Addon Audit Closeout

Date: 2026-11-26. This is an archival record of the 20-item Addon audit,
not a new standard. The living contracts linked below govern subsequent work.
The last product-code merge in this series was `48dbee1` on `master`.

## Dispositions

PR numbers refer to this repository. The dispositions distinguish changes to
behavior from accepted constraints or corrected expectations.

| ID | PRs | Disposition |
| --- | --- | --- |
| H1 | #368 | Uninstall purges the Site Knowledge delivery envelope. Disconnect retains recoverable receipts. The cleanup test now seeds a structured lifecycle value. |
| H2 | #369 | Validate public DNS addresses for each outbound request. At #369, the validation-to-connection window remained. See the [connector boundary](cloud-addon-boundary.md) for the current transport policy. |
| M1 | #370, #378 | Align Nightly Inspection and retry signatures with PHP. Generate and verify the PHP signature reference instead of maintaining parallel method lists. |
| M2 | #376 | Index missing top-level documents and add an index coverage guard. |
| M3 | #371, #380 | Add a native MySQL reconciliation gate and exercise one prepared production query with an equal-timestamp ID boundary. No separate `get_posts()` pagination fallback remains. |
| M4 | #372 | Make settings reads side-effect free; persist upgrades on the explicit upgrade path. |
| M5 | #370 | Mark runtime retry as paused before signing or HTTP. A documented method does not imply the action is available. |
| M6 | #371, #376, #380 | Keep the local fast gate distinct from the full PR gate. Add behavior and native database evidence for the reconciliation query rather than inferring correctness from source-string assertions. |
| M7 | #373 | Document caller-owned WordPress capability, object access and applicable nonce checks. Test denial at the Addon admin entry point; trusted PHP transport is not a user-authorization layer. |
| M8 | #376 | Replace machine-specific checkout paths and guard against their return in the indexed repository documentation. |
| M9 | #374, #381 | Disconnect clears connection diagnostics and invalidates authorization state. The monitoring prompt answer remains site-level until uninstall. Invalidated cache entries expire normally; no key sweep. Fence credential persistence against a concurrent disconnect or replacement. |
| M10 | #375, #382 | Bound the envelope and credential scopes. At capacity, reclaim only a provably scan-only scope; preserve receipts, intents and unknown state, and reject the new write if no eligible scope exists. |
| M11 | #376, #381 | Separate the PHP support floor from the analyzer runtime. Cap baseline entries and suppressed occurrences, and remove obsolete suppressions. A failing workflow and GitHub required-check settings are separate facts. |
| M12 | #377, #379 | Skip the compatibility matrix for verified docs-only PRs. Retry the narrowly classified startup incident once; without success the stable lane remains unverified and the aggregator fails. No measured flake rate or branch-protection change is claimed. |
| L1 | #374 | Bind the one-time authorization state to its initiating WordPress user. |
| L2 | #376 | Call the maintenance option a delivery-state envelope; keep the compatible storage name. |
| L3 | #376 | Ignore AppleDouble files in Git and distribution inputs. |
| L4 | #376 | Describe the boundary grep as a lexical tripwire, not a complete WordPress write barrier. Use code review and behavior checks for the affected integration. |
| L5 | #376 | Enforce the Meta Description pause by stable feature slug. Remaining exact-text matches affect display only and need a check when upgrading WordPress AI. |
| L6 | #367 | Reject malformed connection credentials at input boundaries rather than silently transforming them. The merged implementation preserves supported credential formats and adds negative tests. |

## Evidence and limits

- The audit PRs #367–#382 were squash-merged. The final product PR (#382)
  passed all 15 reported checks; the merge commit passed both CI and PHPStan.
  The earlier post-merge CI run for #379 failed at the compatibility route
  startup, so the final green run is not evidence of zero startup incidents.
- Native MySQL runs covered the reconciliation cursor, conditional credential
  persistence, and a competing Site Knowledge receipt write. Isolated browser
  checks with Cloud doubles cover local behavior, not live Cloud, provider
  quality, or production acceptance.
- Source merges and CI do not establish WordPress.org publication,
  deployment, or cross-system acceptance. This series did not change the
  Cloud-owned runtime or index lifecycle contracts.

## Lessons to reuse

1. **Test the stored shape and the path production uses.** A scalar cleanup
   fixture cannot exercise a structured lifecycle envelope. A fallback query
   cannot prove the production cursor advances across records in the same
   second. Seed competing writes when correctness depends on atomicity.
   See the [verification evidence standard](verification-evidence-standard.md).
2. **Name the state lifetime explicitly.** Disconnect, credential replacement,
   expiry and uninstall are different transitions. Enforce invalidation when
   a result would be committed; prune only state whose loss is recoverable.
   See the [admin standard](admin-surface-standard.md) and
   [delivery contract](site-knowledge-full-index-delivery.md).
3. **Separate mechanical checks from design guarantees.** Generate signatures
   from PHP; keep policy and caller duties in prose. Label what the DNS guard,
   grep and CI actually prove. See the
   [connector boundary](cloud-addon-boundary.md),
   [runtime contract](cloud-runtime-client-contract.md) and
   [repository verification rules](../AGENTS.md).
4. **After squash merge, verify content before deleting a branch.** Match the
   PR head and merge commit, and compare any diverged local patch separately.
   A clean primary checkout does not prove the other worktrees are clean.
