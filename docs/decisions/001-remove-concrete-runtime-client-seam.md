# ADR-001: Remove the Public Concrete Runtime Client Seam

## Status

Accepted on 2026-08-13. Implemented by `npcink-cloud-addon` PR #90.

## Context

The addon historically exposed:

```php
npcink_cloud_addon_runtime_client(): ?Npcink_Cloud_Runtime_Client
```

The helper returned the concrete signed transport client. It was convenient,
but it made transport implementation details observable to sibling plugins and
allowed callers to bypass the scenario-specific facade contracts owned by this
repository.

The project was still in internal development and had no external users. All
maintained consumers could therefore be identified and migrated before a
public compatibility promise formed. The maintained Toolbox, Adapter, Eval
Lab, and addon-owned consumers were moved to named facades before removal.

The decision had to preserve these constraints:

- the addon remains a Cloud connector rather than a generic Cloud proxy;
- callers use fixed scene contracts and endpoint allowlists;
- signing credentials remain private and are never printed or logged;
- Cloud remains runtime/detail only;
- local Core keeps approval, proposal, and WordPress write truth;
- existing facade parameters, result shapes, and fail-closed behavior remain
  unchanged.

## Decision

Remove `npcink_cloud_addon_runtime_client()` instead of extending its
deprecation period.

Addon-owned facade functions construct configured transport clients through
`Npcink_Cloud_Runtime_Client_Factory`. The factory is an implementation detail:
it is not listed in the public PHP interface and must not be used by sibling
repositories.

External integrations must use a scenario-specific public facade. A new
integration that cannot be represented by an existing facade requires a
contract and ownership review before a new named facade is added.

## Alternatives Considered

### Keep the deprecated helper indefinitely

- Advantage: no immediate compatibility break.
- Rejected: there were no external users requiring compatibility, and keeping
  it would turn an accidental convenience API into a permanent contract.

### Rename the helper to an `internal` global function

- Advantage: small code change.
- Rejected: PHP global functions remain callable regardless of naming. This
  would only rename the leak rather than restore the module boundary.

### Let each facade construct the client directly

- Advantage: no new factory class.
- Rejected: repeated configuration checks and construction would be easier to
  drift. One internal factory keeps the invariant explicit without creating a
  public integration surface.

### Expose a generic request method instead

- Advantage: maximum caller flexibility.
- Rejected: a generic proxy would weaken endpoint allowlists, scene contracts,
  and addon ownership boundaries. Flexibility belongs in reviewed named
  contracts, not arbitrary signed transport access.

## Consequences

- Maintained consumers are coupled to stable scenario contracts rather than a
  concrete transport class.
- The addon can change client internals without coordinating every consumer.
- Old unpublished branches that still call the removed helper cannot be paired
  with the new addon until they update to a facade.
- Static and Playground contracts must continue proving that the global helper
  is absent and the internal factory is not documented as public API.
- Removing another public seam requires the same consumer inventory and
  evidence; “no known users” must be verified rather than assumed.

## Removal Preconditions for Future APIs

A public or observable seam may be removed before general availability only
when all of the following are true:

1. Product status and user status are explicit: internal development, no
   external users, and no published compatibility commitment.
2. The authoritative remote branches of every maintained consumer have been
   searched, not only the current local worktrees.
3. Consumers have migrated to the intended replacement and their repository
   gates pass.
4. Addon-owned callers, tests, docs, release manifests, and disposable runtime
   smokes have migrated.
5. A regression contract prevents the removed seam from returning under a new
   name or through documentation.
6. Rollback is source-only and does not require a data, credential, or Cloud
   deployment rollback.

If any precondition is false, use a documented deprecation and migration window
instead of direct removal.

## References

- [Runtime seam closeout and engineering standard](../runtime-seam-closeout-and-engineering-standard-2026-08-13.md)
- [Adapter integration interface](../adapter-integration-seam.md)
- [Cloud Addon boundary](../cloud-addon-boundary.md)
- [Cloud Addon complexity budget](../cloud-addon-complexity-budget.md)
