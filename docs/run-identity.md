# Run identity and provider acknowledgement

## Problem

A network response is not a durable execution identity. The host must create and persist one local `Run` before making a remote dispatch call, then bind the provider execution identity without replacing the local identity or losing the evidence behind the decision.

## Immutable provenance

`RunProvenance` captures:

- execution request, canonical task, and exact task-prompt version identities;
- prompt compiler version and provenance hash without compiled prompt bytes;
- the complete selected-target snapshot, including runtime, repository, workspace authority, observation, and selection time;
- named policy versions;
- initiating actor and local creation time.

## Identity workflow

1. Create and persist a `Run` in `not_dispatched` state.
2. Move it to `awaiting_acknowledgement` immediately before the host invokes the provider.
3. Bind the first acknowledgement only when its provider and target match the immutable target snapshot.
4. Return `duplicate` when the same provider execution identity arrives again.
5. Preserve the first binding and return `conflict` when another provider identity is presented or the identity already belongs to another Run.
6. Record `acknowledgement_uncertain` when dispatch may have succeeded but its response is lost.
7. Use `ProviderExecutionLookup` to reconcile. A verified match acknowledges the existing Run; absent or unavailable lookup leaves it `reconciliation_required`.

The package does not create a second Run after acknowledgement uncertainty.

## Persistence boundary

`RunStore` is the host persistence port. Its adapter must atomically enforce unique `RunId` and `ProviderExecutionId` values and surface `RunIdentityConflict`. The package fake provides the same conformance behavior. Provider clients and credentials remain in the host adapter.

`RunIdentityReadModel` exposes identities, provenance, binding state, and reconciliation issues without compiled prompt bytes or credentials.
