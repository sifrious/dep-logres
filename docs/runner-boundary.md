# First-class runner boundary

The canonical Logres **runner** is a durable, provider-neutral machine-side execution node. It consumes an authorized immutable execution envelope, validates local admissibility, invokes a runtime only through Wardrobe, reports normalized events, and retains enough local state to reconcile a restart without silently invoking the provider twice.

Public product language may say “execution node.” In Logres code the one domain term is `Runner`; `RunnerIdentity::asExecutionNode()` maps that identity to the existing lease-holder `ExecutionNodeRef`. “Machine,” “worker,” “orb,” and provider names are not synonyms for this abstraction.

## Flow

```text
Hosted Harness / control plane
        |
        v
authorized immutable ExecutionEnvelope
        |
        v
ExecutionRunner -- validates target, grant, workspace, runtime, capability, lifecycle
        |
        v
provider-neutral RuntimeRequest
        |
        v
host Wardrobe bridge -> Wardrobe RuntimeInvocation -> selected RuntimeAdapter
```

`RunnerRuntime` is the Logres-side port. `WardrobeRunnerRuntime` is its concrete bridge to Wardrobe's `RuntimeInvocation`, `RuntimeObserver`, and selected `RuntimeAdapter`. Provider choice and provider-specific output translation remain behind Wardrobe. `ExecutionRunner` must never import provider SDKs, invoke provider commands, or branch on Codex, Claude, Amp, or another provider.

## Ownership

| Owner | Authoritative responsibilities |
| --- | --- |
| Logres | Run, Attempt and Lease identity; legal lifecycle transitions; grants; retry, cancellation, timeout and idempotency semantics; runner contract; normalized runner events and terminal-result contract. |
| Wardrobe | Provider-neutral runtime invocation, runtime capabilities and adapter-selection inputs, concrete provider adapters, provider-output translation at the runtime boundary. |
| Runner process | Machine identity integration, outbound work receipt, local envelope validation, local workspace checks, Wardrobe invocation, event forwarding, restart reconciliation and machine lifecycle integration. |
| Hosted Harness / Laravel control plane | Account authorization, queues and APIs, runner assignment, target selection, persistent control-plane state and user-facing state. |
| Stacks | Durable repository/workspace identity and current machine-local workspace observations. |
| Funes | Append-only historical evidence, provenance and history; never authoritative current Run state. |
| Orbis | Portable agent definitions and templates; not machine-side execution. |

## Acceptance

`ExecutionRunner::execute()` accepts raw envelope data so parse failures become an explicit `malformed` outcome. After parsing, it checks protocol support, authenticity, expiration, target runner, grant authorization, Stacks-backed workspace/repository consistency, Wardrobe adapter availability, capabilities, canonical Logres lifecycle authority, and prior local acceptance state. Expected failures use `RunnerRejectionReason`; they do not invoke Wardrobe and do not rely on generic exceptions.

Authenticity, authorization, workspace observation, and lifecycle decisions are ports because their canonical data belongs outside the runner object. `ExecutionStateRunnerLifecycle` consumes the canonical Logres Run/Attempt/Lease state introduced by MME-1807 and validates the active Attempt, Lease, holder, token, and expiry. It is not a second lifecycle engine.

## Events and terminal results

Every `RunnerEvent` contains stable event identity, Run, Attempt, Lease and Runner identities, a positive sequence, timestamp, typed event, and payload. Event IDs are deterministic for an execution and sequence, so delivery may safely be retried. The browser or UI is never the delivery authority.

`RunnerTerminalResult` uses a typed status: `success`, `failure`, `cancelled`, `timed_out`, or `rejected`. Provider prose does not determine success. Logres preflight, postflight, and lifecycle rules remain authoritative when a host commits the result.

## Restart and duplicate delivery

The runner durably records `received`, `accepted`, `invoking`, `reporting`, and `terminal`, keyed by immutable Run + Attempt + Lease identity. The store implementation must serialize concurrent writes and persist before returning.

- A duplicate of terminal work returns the retained terminal result.
- A duplicate found at accepted, invoking, or reporting is rejected/reconciled and never invokes Wardrobe again.
- The terminal result is saved in `reporting` before terminal event delivery, so lost network acknowledgement is not permission to execute again.
- Terminal Logres state cannot reopen; retry requires canonical MME-1807 Attempt/Lease semantics and a new authorized identity.

This local record is reconciliation evidence, not authoritative current lifecycle state. Recovery policy that decides whether an interrupted provider execution can be resumed remains an explicit dependency on MME-1807 and the selected Wardrobe adapter's provider-binding behavior.

## Outbound-only operation

The runner contract has no HTTP controller, route, socket, queue-worker, or listener abstraction. A host can receive envelopes through authenticated long polling, an outbound-initiated WebSocket, or a future outbound stream. No publicly reachable inbound port is required.

## What is not a runner

A runner is not a UI session, Laravel queue worker, Wardrobe/provider adapter, Logres state machine, repository, arbitrary shell script, AI agent definition, or Amp Orb. Daemons and managed services may host a runner; they do not redefine it.

## Human verification record

```text
Repository: sifrious/dep-logres
Commit SHA: <record after commit>
Branch: <record branch>
Environment: PHP 8.3+
Exact command: composer check
Expected exit code: 0
Actual exit code: <record>
Named tests: RunnerConformanceTest; ExecutionStateTest; PackageBoundaryTest
Result: <record>
```

From a clean checkout, `composer install && composer check` proves valid execution invokes the fake Wardrobe boundary once, invalid envelopes fail closed, rejected work invokes zero runtimes, normalized events and terminal results are produced, duplicate/restart state prevents a second invocation, no inbound listener is required, and package boundaries remain framework/provider neutral.
