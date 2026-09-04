# Current execution state

## Ownership

Logres is the sole authority for current execution state and its legal mutations. Funes may retain append-only historical evidence, but is not queried to determine current state. Wardrobe invokes a selected runtime. Stacks supplies workspace identity and provenance. The application host supplies persistence, transactions or compare-and-swap, timers, transport, queues, controllers, and presentation.

Provider facts and callbacks are inputs to package commands. They cannot assign a status directly or bypass `ExecutionState`, `ExecutionLease`, or `RunTransitionPolicy`.

## Run, Attempt, and Lease

A Run is one stable logical execution, such as `run:42`. Attempt `attempt:42:1` is its first concrete try. Lease `lease:abc` gives `node:mac-1` bounded authority to act on that Attempt until its expiry. If execution needs a later try, `attempt:42:2` names `attempt:42:1` as its predecessor; the first Attempt is never rewritten into the second. A Lease belongs to exactly one Attempt and an Attempt belongs to exactly one Run.

`LeaseToken` is authority-bearing and is deliberately omitted from `ExecutionStateReadModel`. Hosts must store and transmit it as a secret.

### Landing `AgentTask` migration map

The earlier application slice in `sifrious/logres-site#11` was reconciled into this aggregate rather than copied into a second owner. Its lifecycle vocabulary maps as follows: `queued` → `pending`, `provisioning` → `preparing`, `running` and `merging` → `running` (the runtime/result references distinguish the phase), `awaiting_approval` and `frozen` → `needs_input`, `merged` → `succeeded`, `rejected` → `failed`, and `cancelled` → `cancelled`.

Original columns have explicit package homes: Run identity owns `id`; immutable `ExecutionStateDetails` owns `repo_id` as a Stacks workspace identity, `parent_task_id`, title, prompt, base/working branch, worktree/SQLite evidence paths, PR/result metadata, output evidence path, creator/approver identities, approval time, runtime invocation, target reference, and update observation; `ExecutionState` owns status, scheduled/start/finish timestamps and current error; `createdAt` owns `created_at`. `recordApproval` and `recordExecutionResult` change this current slice through aggregate methods. The read model exposes these values without treating paths as workspace identity.

## Run state machine

The package's complete legal transition graph is defined by `RunTransitionPolicy`:

| Source | Command/event | Destination | Invariant | Exact replay | Rejection | Terminal |
|---|---|---|---|---|---|---|
| `pending` | schedule first Attempt | `preparing` | no active Attempt | converges at command boundary | `invalid_transition` | no |
| `pending` | cancel | `cancelled` | host supplies package cancellation command | converges | `invalid_transition` | yes |
| `preparing` | start leased Attempt | `running` | current Attempt and active matching Lease | converges | `stale_attempt`, `foreign_lease`, `lease_expired` | no |
| `preparing` | observe uncertain/retryable failure | `reconciling` | current Attempt, active matching Lease, classified failure | operation identity converges | `stale_attempt`, `foreign_lease`, `cancellation_pending` | no |
| `preparing` | human gate | `needs_input` | package gate accepts input requirement | command-defined | `invalid_transition` | no |
| `preparing` | fail / timeout / cancel | matching terminal state | current package command is valid | converges | `invalid_transition` | yes |
| `running` | human gate | `needs_input` | package gate accepts input requirement | command-defined | `invalid_transition` | no |
| `running` | observe uncertain/retryable failure | `reconciling` | classified failure and retry policy | operation identity converges | `stale_attempt`, `foreign_lease`, `cancellation_pending` | no |
| `running` | finish | `succeeded`, `failed`, `timed_out`, or `cancelled` | current Attempt, active matching Lease, terminal result | identical result converges | `already_terminal`, `stale_attempt`, `foreign_lease`, `lease_expired` | yes |
| `needs_input` | resume | `preparing` | required input is supplied | command-defined | `invalid_transition` | no |
| `needs_input` | fail / timeout / cancel | matching terminal state | current package command is valid | converges | `invalid_transition` | yes |
| `reconciling` | schedule linked retry | `preparing` | retry action, no active Attempt | converges through stored Attempt identity | `reconciliation_required`, `already_terminal` | no |
| `reconciling` | provider acceptance confirmed | `running` | same Attempt and active matching Lease | converges | `foreign_lease`, `lease_expired` | no |
| `reconciling` | exhausted/permanent failure, cancel, or timeout | matching terminal state | policy decision or accepted cancellation | converges | `already_terminal` | yes |
| any terminal state | any transition | none | terminal state is immutable | only the identical accepted terminal operation converges | `already_terminal` | yes |

The closed status vocabulary is `pending`, `preparing`, `running`, `reconciling`, `needs_input`, `succeeded`, `failed`, `timed_out`, and `cancelled`. `RunTransitionPolicyTest` enumerates every allowed edge. All other edges reject. Current state has no public arbitrary-status setter.

## Attempt and lease lifecycle

An Attempt begins `ready`. Acquisition creates an `active` Lease and makes the Attempt `leased`. Starting makes it `running`. An elapsed active Lease can be explicitly expired, making the Attempt `expired`. Policy consumers may either reclaim that same non-terminal Attempt with a new Lease or create a new, linked Attempt with `nextAttemptAfterExpiry`. Completion makes the Attempt `succeeded` or `failed` and the Run terminal.

- Acquire requires the current non-terminal, ready or expired Attempt. Replaying the same acquisition identity with the same authority returns the same logical Lease. A different contender receives `already_leased`.
- Renew requires the holder, token, active status, and a time before expiry. The same renewal identity converges. Foreign/stale authority and elapsed leases reject explicitly.
- Release requires the holder and token. The same release identity converges. A released Lease cannot renew.
- Expire is allowed only at or after expiry. Repeated expiry converges.
- Reclaim never changes a terminal Attempt or Run. A distinct retry has a new Attempt identity and explicit predecessor.

The invariant is: **at most one active Lease authorizes execution of one Attempt**. Aggregate construction enforces it in memory. `ExecutionStateStore::compareAndSwap` lets a host enforce it atomically across processes; `ExecutionStateService::acquireLease` gives exactly one competing acquisition a winning state version.

## Retry and recovery

`FailureClassification` separates transient, permanent, and acknowledgement-uncertain observations. `RetryPolicy` deterministically returns `retry`, `reconcile`, or `fail` from the classification and Attempt count. The operation identity makes duplicate failure observation converge and rejects conflicting reuse.

A transient retry closes the failed Attempt, moves the Run to `reconciling`, and permits exactly one new linked Attempt. A permanent or exhausted failure terminates the Run. Lost acknowledgement retains the same Attempt and Lease while provider lookup/reconciliation is pending; confirming remote acceptance resumes that Attempt and never dispatches a duplicate. `RecoveryRecord` is part of persisted current state, so restart/reload produces the same decision.

## Cancellation and timeout

Cancellation requires an explicit `CancellationAuthorization`. Before dispatch it immediately terminates without invocation. During active execution it durably records `requested` intent; while pending, acquisition and renewal reject with `cancellation_pending`. Confirmation requires the matching Attempt and Lease token, releases authority, retains a partial-result reference, and terminates as either `cancelled` or `timed_out` according to `CancellationKind`.

Request and confirmation replay converge by operation identity. Reusing an identity for different intent rejects with `cancellation_conflict`. A lost provider acknowledgement leaves the requested intent visible and reconcilable rather than pretending cancellation completed. Terminal Runs cannot accept a new cancellation or retry.

## NeedsInput and Elwin handoffs

Elwin owns clarification questions, allowed response shapes, accepted responses, resumable handoff identity, handoff payload, and resume context. Logres consumes those contracts; it does not define parallel clarification or handoff records.

`ExecutionState::pauseForInput` accepts an Elwin `ResumableHandoff` that is awaiting response and references the current Logres Run plus a Logres Turn checkpoint. It releases active Lease authority, keeps the same Attempt active with `needs_input` status, and persists only:

- the versioned Elwin handoff reference;
- Elwin's opaque `ResumeContext`;
- the current Attempt identity;
- Logres pause and resolution status, timestamps, and idempotency operation identity.

The clarification prompt, response shape, response value, and display payload remain reachable through Elwin and are not copied into Logres lifecycle state. Exactly one handoff may be outstanding. Exact pause replay converges; changed or competing handoffs reject.

After Elwin accepts a response, `resumeFromInput` requires a matching resumable handoff and a host-supplied authorization decision. It returns the same Attempt to `ready` and the Run to `preparing`; no retry Attempt or second state machine is created. Elwin-observed expiry maps to Logres `timed_out`, while Logres cancellation remains `cancelled`.

`TurnCheckpointStore` persists the resolved `TurnContext` after invariant preflight and before-turn handlers complete. If the harness raises `NeedsInput`, `TurnRunner` stores that checkpoint and rethrows the pause signal. `TurnRunner::resume` validates the answered Elwin handoff and restores the checkpoint, so completed handlers are not invoked again. MME-1007 owns the surrounding orchestration and delivery. MME-1010 owns limits and expiry policy. Elwin/MME-1496 owns handoff representation. Hosts own browser connections, timers, authentication, storage adapters, and UI.

## Adapter boundary

Logres guarantees identities, state invariants, transition legality, structured rejection reasons, idempotent mutation semantics, non-secret read models, lineage, and optimistic-concurrency semantics. A host must durably serialize the full aggregate, enforce unique Run identity, implement atomic compare-and-swap on `version`, generate unpredictable lease tokens and unique command identities, provide a trusted clock, and schedule expiry observation. Database locks, unique constraints, HTTP, queues, provider SDKs, and framework models remain host adapters.
