# Logres Package

This repository contains the framework-neutral contracts and execution kernel used by the Logres application.

It deliberately has no Laravel, Eloquent, queue, HTTP, Blade, NativePHP, or concrete process dependency.

The current consumer surface is recorded in [PUBLIC-API.md](PUBLIC-API.md).

## Development

```bash
composer install
composer check
```

Local applications consume this package through a Composer path repository during development.

## Execution requests

Logres owns immutable execution request identity, resolved executable work, validation, lineage, submission results, and presentation-neutral read models. When work is materialized from deliberation, `DeliberationOrigin` preserves upstream input, intent, conversation, plan, and step references without making Logres own those domains. Hosts provide authentication, authorization integration, identity generation, attachment storage, persistence adapters, and delivery.

An original request has no parent. A correction or child request receives a new identity and references its parent; neither operation mutates the earlier request. A request is accepted only after the configured store returns successfully.

## Task plans

Logres translates an accepted request into canonical tasks with stable identities, explicit outputs and acceptance evidence, dependency and readiness rules, concurrency and human-input declarations, package-owned status transitions, and re-planning lineage. The default deterministic planner produces two parallel discovery tasks followed by implementation and verification. Hosts persist and render the package plan; they do not recalculate readiness or available actions.

## Task prompts

Logres compiles one deterministic, immutable execution envelope for a canonical task. The envelope preserves the original request, task contract, prerequisite outputs, resolved context, project instructions, selected skills and tools, permissions, result contract, reporting contract, compiler identity, content hashes, and version lineage. Recompiling identical declared inputs returns the existing version; any declared input change creates a linked version without mutating prior bytes.

## Execution targets

Logres selects one concrete execution target from provider facts supplied through an `ExecutionTargetCatalog`. The package owns requirements, eligibility, explicit authorization, capability matching, health interpretation, automatic and manual selection, immutable target snapshots, failure reasons, and presentation-neutral read models. Hosts own provider authentication, inventory adapters, persistence adapters, and delivery. A task cannot be dispatched from an absent, ambiguous, unavailable, incapable, or unauthorized selection.

## Run identity and provider acknowledgement

Logres creates a stable local `Run` and immutable provenance snapshot before dispatch. The package binds the first matching provider acknowledgement, treats duplicates as idempotent, rejects conflicting or cross-Run provider identities, and models uncertain acknowledgement and explicit reconciliation. Hosts provide provider lookup and persistence adapters; storage must enforce unique local and provider execution identities atomically.

## Current execution state

Logres is the authority for mutable current execution state. `ExecutionState` separates a stable Run, each concrete `ExecutionAttempt`, and each bounded `ExecutionLease`; `ExecutionStateReadModel` exposes the current Attempt, lease holder and expiry, terminal result reference, failure reason, and complete Attempt lineage without requiring event replay. See [docs/execution-state.md](docs/execution-state.md) for the state machine, lease lifecycle, ownership boundary, and host persistence contract.

## Dispatch authorization

Logres requires an explicit, current grant before a Run can enter dispatch. The policy matches canonical repository identity, workspace authority and normalized contained path, selected target, environment, runtime, frozen prompt permissions, actor, grant validity, and target-observation freshness. An allowed decision freezes the approved context on the Run. Capabilities describe what a target can do; only a grant describes what it may do.
