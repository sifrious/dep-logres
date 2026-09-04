# Runner boundary

Logres defines the canonical, provider-neutral description of a runner. A runner is an execution node that can be discovered and evaluated before dispatch without importing a provider SDK or a host framework.

## Contracts

- `RunnerIdentity` provides a stable identity in the `runner:` namespace and maps to the existing `ExecutionNodeRef` lease-holder identity.
- `PlatformIdentity` records the operating system and architecture reported by the host.
- `CapabilitySnapshot` records normalized capabilities, Wardrobe runtime-adapter references, supported protocol versions, and the observation time.
- `RunnerAvailability` distinguishes available, busy, draining, and offline runners.
- `CurrentWorkload` records active work against positive capacity.
- `RunnerDescriptor` combines those observations with stable authorization-grant references.

Arrays in a capability snapshot are non-empty, de-duplicated, and sorted so equivalent observations have deterministic values. Each snapshot carries a content-derived `capability-snapshot:` version covering its normalized observations and timestamp. Changed observations therefore create new immutable versions without rewriting the version frozen into prior Run provenance. Snapshot staleness is evaluated explicitly and remains distinct from current `RunnerAvailability`. The snapshot is evidence observed at a point in time; it does not grant dispatch authority.

## Ownership

Logres owns the compatibility contracts and their validation. Burdgeon owns discovery, heartbeat integration, persistence, configuration, and pre-dispatch use of runner observations. Wardrobe owns runtime adapter profiles; descriptors reference adapter identities rather than duplicating their definitions. Stacks supplies stable workspace and grant identities.

The boundary deliberately contains no HTTP, queue, UI, framework, provider-SDK, or process-launching dependency.

## Envelope acceptance and runtime invocation

`ExecutionRunner` is the provider-neutral machine-side orchestration boundary. It parses an immutable `ExecutionEnvelope`, verifies protocol support and authenticity, rejects expired or wrongly addressed work, checks the stable grant and Stacks workspace/repository observation, confirms runtime and capability availability, and asks the canonical Logres lifecycle gate to validate the active Run, Attempt, and Lease.

Expected failures return `RunnerRejectionReason`; rejected work never invokes the runtime. Accepted work becomes a `RuntimeRequest` through `RunnerRuntime`. A Burdgeon runner process binds that port to Wardrobe. Logres neither imports provider SDKs nor branches on Codex, Claude, Amp, or any other provider.

## Outbound poll, lease acknowledgement, and terminal reconciliation

`RunnerPollRequest`/`RunnerPollResponse` and `RunnerWorkPoller` define the outbound work-fetch contract. A poll returns either one offered bounded lease (`lease`) with its immutable `ExecutionEnvelope`, or explicit `no_work` with a positive retry delay.

`RunnerLeaseAcknowledgement`, `RunnerLeaseAcknowledger`, and `RunnerLeaseAcknowledgementResult` define idempotent lease acknowledgement. Hosts replay the same acknowledgement identity safely and reject identity reuse for different lease authority.

`RunnerTerminalResultSink` and `RunnerTerminalReconciler` define network-loss recovery after local execution reaches a terminal result. When local state is `reporting`, reconciliation replays the retained terminal result; accepted/duplicate receipts converge local state to `terminal` without re-invoking Wardrobe.

## Outbound runner loop

`OutboundRunnerLoop` performs one transport-neutral cycle: poll, return bounded backoff when no work is available, acknowledge an offered lease, invoke `ExecutionRunner` only after an acknowledged or duplicate acknowledgement, and report any terminal result. Rejected or conflicting acknowledgements fail the cycle before runtime invocation. A retry receipt leaves the durable local record in `reporting`, where `RunnerTerminalReconciler` can redeliver it without invoking the runtime again.

Acknowledgement identities use `runner-ack:sha256:<hex>`, where `<hex>` is SHA-256 over the UTF-8 Run ID, Attempt ID, and Lease ID values in that order, separated by NUL bytes. Replaying one offered lease therefore produces the same acknowledgement identity.

The loop depends only on package contracts. HTTP authentication, long-poll request construction, endpoint URLs, serialization, and other transport adapters belong to the host application.

## Events and terminal outcomes

Every `RunnerEvent` contains stable Run, Attempt, Lease, Runner, event, sequence, and timestamp identities. Its deterministic event ID permits safe redelivery. The normalized vocabulary includes acceptance, start/running/status, output, questions/intervention, artifacts, warnings/failures, and one typed terminal result.

`RunnerTerminalResult` distinguishes success, failure, cancellation, timeout, and rejection. Provider prose is evidence, not the authority that decides success.

## Restart and duplicate delivery

`RunnerLocalStateStore` durably records `received`, `accepted`, `invoking`, `reporting`, and `terminal`, keyed by immutable Run + Attempt + Lease identity. A repeated terminal delivery returns the retained terminal result. A restart that finds accepted, invoking, or reporting work fails closed for reconciliation and cannot silently invoke Wardrobe again. The terminal result is stored before reporting, so loss of a network acknowledgement is never permission to execute twice.

Before runtime invocation, the store atomically reserves both the transport
execution key and the logical idempotency identity. The reservation carries a
canonical fingerprint of immutable work excluding replaceable lease authority.
An exact replay under another lease converges on the existing record; reuse of
either identity for a different Run, Attempt, routing context, or payload is an
`idempotency_conflict`. This atomic reservation, rather than a read followed by
a write, is the concurrency boundary that prevents two workers from invoking
the same logical work.

Local runner state exists only for reconciliation and duplicate prevention. `ExecutionStateRunnerLifecycle` consumes Logres's canonical state and lease authority; it does not create a competing lifecycle.

## Outbound-only hosting

The runner contract has no listener, HTTP controller, route, queue worker, or public-port requirement. Hosts may receive work through authenticated long polling, outbound WebSockets, or future outbound streams.

## What is not a runner

A runner is not a UI session, Laravel queue worker, Wardrobe adapter, provider SDK, Logres state machine, repository, arbitrary shell script, portable Orbis agent definition, or Amp Orb specifically.
