# Execution Targets

Every dispatched task must refer to one concrete, persisted target snapshot. Execution may not derive authority from a current machine, current directory, hidden provider default, or caller-supplied ID. A snapshot preserves the provider-qualified target and machine identities, provider account/project, execution class, observed availability and health, runtime/image, workspace authority, repository identity, supported adapters and capabilities, workload, observation time, selection policy, request/override provenance, and every candidate evaluation.

## Workflow

1. The host maps a canonical task to `ExecutionTargetRequirements`.
2. A provider adapter returns factual `ExecutionTargetCandidate` values through `ExecutionTargetCatalog`.
3. `ExecutionTargetSelector` evaluates every candidate against provider context, workspace/repository identity, execution class, adapter, capabilities, health, availability, concurrency, freshness, and explicit authorization. Each failure uses `CandidateRejectionReason` rather than presentation prose.
4. Automatic selection sorts eligible candidates by stable provider-qualified identity using policy `execution-target-selection-v1`; provider response or database order cannot change the winner. Manual selection is only a preference and applies the identical evaluation policy.
5. The host persists the immutable `ExecutionTargetSelection` through `ExecutionTargetStore` before dispatch.
6. Hosts render `ExecutionTargetReadModel` and its preserved candidate evaluations; they do not recompute package rules. Dispatch preflight consumes the frozen selection through `RunProvenance` and fails closed when provenance or authorization is absent.

## Outcomes

- `target_unavailable`: no matching target exists or every capable target is unavailable, degraded, unhealthy, or busy.
- `target_incapable`: contextual targets exist but none satisfies the required adapter and capabilities.
- `target_unauthorized`: operational targets exist but none is explicitly authorized for the target, workspace, and repository.
- stable candidate reasons include authorization, workspace/repository, account/project, capability/runtime, health/availability, freshness, concurrency, execution-class, inventory-identity, privacy, and network failures.

A manual override records requested and resolved target identity plus override actor. Unknown/free-form IDs return `target_not_in_inventory`; they can never become execution authority.

## Terminology

- **execution target** — the frozen, provider-neutral destination selected for one task/run version.
- **candidate** — one immutable provider observation offered to the selector.
- **capability snapshot** — the candidate's observed runtime/tool abilities at `observedAt`.
- **health observation** — provider-reported health, availability, workload, and observation timestamp.
- **selection policy** — versioned deterministic eligibility and tie-break rules.
- **requested target** — an optional preference submitted for manual override.
- **resolved target** — the eligible candidate actually frozen for dispatch.
- **override** — a preference that remains subject to normal authorization and eligibility.
- **execution class** — local, managed-cloud, customer-owned, or provider-hosted placement.
- **freshness** — the maximum allowed age of provider observations at selection/preflight.
- **workspace authority** — the stable grant identity authorizing this workspace; a path is not identity.

## Decisions and rejected alternatives

Snapshots are immutable because mutable inventory cannot explain a historical dispatch after health, runtime, or access changes. Provider adapters report facts while Logres decides authorization. Overrides never bypass policy. Repository paths are presentation/location data, not machine or workspace identity. Provider response order is not policy; stable identity is the v1 tie-break. Real Amp/Orb discovery remains behind `ExecutionTargetCatalog` so deterministic fake inventories prove the kernel first.

Rejected designs: a hidden default Orb, selecting the first provider result, controller-owned selection, UI-owned eligibility, a mutable foreign key without history, arbitrary target IDs, and fallback to another execution class after authorization failure.

## Human verification

From a clean checkout of `sifrious/logres` on this branch:

```bash
composer install
composer check
```

`ExecutionTargetSelectorTest` covers deterministic reordered inventories, explicit candidate reasons, valid/invalid overrides, immutable persistence, reloadable read models, and absent inventory. `DispatchAuthorizationPolicyTest` and `RunIdentityConformanceTest` prove that dispatch requires the frozen target and matching workspace/repository authority. Live Amp/Orb discovery, revocation smoke, host persistence, and Burdgeon UI remain consuming-application work and must pass before MME-8 is marked Done.

## Ownership Boundary

Logres contains no Amp or Orb client. The package accepts provider observations without credentials and returns deterministic selection results. Burdgeon must supply the authenticated provider adapter and persistence implementation. Until Amp exposes or Burdgeon possesses a factual Orb inventory and state source, the host must show target discovery as unavailable rather than synthesize an inventory.
