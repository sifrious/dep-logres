# Remote execution lifecycle conformance

`RemoteExecutionLifecycleConformanceTest` composes the package-owned current-state, Attempt, Lease, retry/reconciliation, cancellation, timeout, persistence, finalization, and terminal invariants. It is deliberately a consumer of those contracts, not a second lifecycle implementation.

The suite proves an authorized selected target flows through a bounded Lease, mandatory preflight, one provider invocation, independent successful verification, and one terminal Run. It also proves a transient failure produces a linked second Attempt; stale callbacks and terminal retry cannot reopen it; lost acknowledgement survives reload and resumes the same Attempt/Lease without duplicate dispatch; active cancellation preserves partial evidence; and runtime cancellation remains independent from verification/finalization disposition.

Supporting suites provide the rest of the package matrix:

- `DispatchAuthorizationPolicyTest` and `ExecutionTargetSelectorTest`: unsafe, unauthorized, ambiguous, unavailable, and incapable targets reject before invocation.
- `ExecutionStateTest`: lease contention, replay, renewal, release, expiry, reclaim, read models, and terminal invariants.
- `RecoveryCancellationTest`: retry classification/exhaustion, reconciliation, restart, cancellation authorization, cancel-before-dispatch, cancel-during-run, timeout, and lost-cancel acknowledgement state.
- `LifecycleFinalizationTest`: mandatory preflight, zero invocation after preflight failure, postflight evidence, independent verification, durable local result, historian failure, timeout, and cancellation finalization.

The package gate does not itself prove HTTP double polling or an authenticated remote endpoint. MME-732 must consume `ExecutionStateService`/`ExecutionStateStore` in an application adapter and prove two polls yield one bounded Lease. MME-2105 and MME-1807 remain open until that consuming proof and any remaining cross-package prerequisites are recorded.
