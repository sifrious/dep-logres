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

## Provider-neutral invocation

`ProviderInvocationRequest` is the immutable dispatch intent. It combines the
stable provider invocation and idempotency identities with the Logres Run,
request, task, and Attempt identities; the frozen provider target and selected
agent adapter; the compiled `TaskPrompt`; workspace instructions; event stream
and callback configuration; input-response routing; and explicit timeout and
cancellation references.

`ProviderInvocationCoordinator` reserves that request through the atomic
`ProviderInvocationPersistence` boundary before crossing the `ProviderDispatch`
port. Reservation persists both the invocation and the Run entering
`awaiting_acknowledgement`, while every later transition persists the invocation
record and Run together. The reservation enforces uniqueness for invocation ID
and idempotency key.

A `reserved` replay is safe to resume because the provider boundary has not yet
been crossed. The coordinator durably changes it to `dispatching` before the
provider call. A replay that finds `dispatching` must assume remote acceptance
may have occurred, changes both records to acknowledgement-uncertain, and never
redispatches. Accepted, rejected, unavailable, and acknowledgement-uncertain
outcomes are recorded explicitly. Reconciliation through
`ProviderExecutionLookup` atomically updates the same invocation record and Run.
A provider rejection or confirmed unavailability moves the Run to
`dispatch_failed`; it cannot later accept an acknowledgement. Provider
exceptions are treated as acknowledgement-uncertain because the package cannot
prove that remote acceptance did not occur.

## Persistence boundary

`RunStore` is the host persistence port. Its adapter must atomically enforce unique `RunId` and `ProviderExecutionId` values and surface `RunIdentityConflict`. The package fake provides the same conformance behavior. Provider clients and credentials remain in the host adapter.

The host owns the durable transaction implementing `ProviderInvocationPersistence`
and the provider/queue client implementing `ProviderDispatch`. The package owns
the request shape, reservation semantics, dispatch outcomes, and coordination;
it contains no provider SDK, credentials, queue, or framework dependency.
Implementations must make each interface method one transaction: reservation
must commit the invocation and awaiting Run together, and transition must commit
the next invocation status and Run binding state together. On failure neither
side may change. Every transition is compare-and-swap against the expected
prior invocation status and version. A stale writer must return `false` without
changing either record; the coordinator then re-reads and returns current
durable truth. A positive reconciliation result may retry against a concurrently
recorded unresolved lookup, but an unavailable or not-found observation can
never overwrite an already accepted invocation and acknowledged Run.

`RunIdentityReadModel` exposes identities, provenance, binding state, and reconciliation issues without compiled prompt bytes or credentials.
