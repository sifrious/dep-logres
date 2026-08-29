# Current execution state

## Ownership

Logres is the sole authority for current execution state and its legal mutations. Funes may retain append-only historical evidence, but is not queried to determine current state. Wardrobe invokes a selected runtime. Stacks supplies workspace identity and provenance. The application host supplies persistence, transactions or compare-and-swap, timers, transport, queues, controllers, and presentation.

Provider facts and callbacks are inputs to package commands. They cannot assign a status directly or bypass `ExecutionState`, `ExecutionLease`, or `RunTransitionPolicy`.

## Run, Attempt, and Lease

A Run is one stable logical execution, such as `run:42`. Attempt `attempt:42:1` is its first concrete try. Lease `lease:abc` gives `node:mac-1` bounded authority to act on that Attempt until its expiry. If execution needs a later try, `attempt:42:2` names `attempt:42:1` as its predecessor; the first Attempt is never rewritten into the second. A Lease belongs to exactly one Attempt and an Attempt belongs to exactly one Run.

`LeaseToken` is authority-bearing and is deliberately omitted from `ExecutionStateReadModel`. Hosts must store and transmit it as a secret.

## Run state machine

The package's complete legal transition graph is defined by `RunTransitionPolicy`:

| Source | Command/event | Destination | Invariant | Exact replay | Rejection | Terminal |
|---|---|---|---|---|---|---|
| `pending` | schedule first Attempt | `preparing` | no active Attempt | converges at command boundary | `invalid_transition` | no |
| `pending` | cancel | `cancelled` | host supplies package cancellation command | converges | `invalid_transition` | yes |
| `preparing` | start leased Attempt | `running` | current Attempt and active matching Lease | converges | `stale_attempt`, `foreign_lease`, `lease_expired` | no |
| `preparing` | human gate | `needs_input` | package gate accepts input requirement | command-defined | `invalid_transition` | no |
| `preparing` | fail / timeout / cancel | matching terminal state | current package command is valid | converges | `invalid_transition` | yes |
| `running` | human gate | `needs_input` | package gate accepts input requirement | command-defined | `invalid_transition` | no |
| `running` | finish | `succeeded`, `failed`, `timed_out`, or `cancelled` | current Attempt, active matching Lease, terminal result | identical result converges | `already_terminal`, `stale_attempt`, `foreign_lease`, `lease_expired` | yes |
| `needs_input` | resume | `preparing` | required input is supplied | command-defined | `invalid_transition` | no |
| `needs_input` | fail / timeout / cancel | matching terminal state | current package command is valid | converges | `invalid_transition` | yes |
| any terminal state | any transition | none | terminal state is immutable | only the identical accepted terminal operation converges | `already_terminal` | yes |

The closed status vocabulary is `pending`, `preparing`, `running`, `needs_input`, `succeeded`, `failed`, `timed_out`, and `cancelled`. `RunTransitionPolicyTest` enumerates every allowed edge. All other edges reject. Current state has no public arbitrary-status setter.

## Attempt and lease lifecycle

An Attempt begins `ready`. Acquisition creates an `active` Lease and makes the Attempt `leased`. Starting makes it `running`. An elapsed active Lease can be explicitly expired, making the Attempt `expired`. Policy consumers may either reclaim that same non-terminal Attempt with a new Lease or create a new, linked Attempt with `nextAttemptAfterExpiry`. Completion makes the Attempt `succeeded` or `failed` and the Run terminal.

- Acquire requires the current non-terminal, ready or expired Attempt. Replaying the same acquisition identity with the same authority returns the same logical Lease. A different contender receives `already_leased`.
- Renew requires the holder, token, active status, and a time before expiry. The same renewal identity converges. Foreign/stale authority and elapsed leases reject explicitly.
- Release requires the holder and token. The same release identity converges. A released Lease cannot renew.
- Expire is allowed only at or after expiry. Repeated expiry converges.
- Reclaim never changes a terminal Attempt or Run. A distinct retry has a new Attempt identity and explicit predecessor.

The invariant is: **at most one active Lease authorizes execution of one Attempt**. Aggregate construction enforces it in memory. `ExecutionStateStore::compareAndSwap` lets a host enforce it atomically across processes; `ExecutionStateService::acquireLease` gives exactly one competing acquisition a winning state version.

## Adapter boundary

Logres guarantees identities, state invariants, transition legality, structured rejection reasons, idempotent mutation semantics, non-secret read models, lineage, and optimistic-concurrency semantics. A host must durably serialize the full aggregate, enforce unique Run identity, implement atomic compare-and-swap on `version`, generate unpredictable lease tokens and unique command identities, provide a trusted clock, and schedule expiry observation. Database locks, unique constraints, HTTP, queues, provider SDKs, and framework models remain host adapters.
