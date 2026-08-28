# Execution Targets

Every dispatched task must refer to a concrete, persisted target snapshot. A target records its provider-qualified identity, observed availability and health, runtime, workspace authority, repository identity, supported agent adapters and capabilities, current task, and observation time.

## Workflow

1. The host maps a canonical task to `ExecutionTargetRequirements`.
2. A provider adapter returns factual `ExecutionTargetCandidate` values through `ExecutionTargetCatalog`.
3. `ExecutionTargetSelector` narrows candidates by provider, workspace authority, repository identity, agent adapter, capabilities, operational state, and explicit authorization.
4. Automatic selection succeeds only when one eligible candidate remains. Manual selection applies the same eligibility and authorization rules to the requested target.
5. The host persists the immutable `ExecutionTargetSelection` through `ExecutionTargetStore` before dispatch.
6. Hosts render `ExecutionTargetReadModel` and `ExecutionTargetCatalogReadModel`; they do not recompute package rules.

## Outcomes

- `target_unavailable`: no matching target exists or every capable target is unavailable, degraded, unhealthy, or busy.
- `target_incapable`: contextual targets exist but none satisfies the required adapter and capabilities.
- `target_unauthorized`: operational targets exist but none is explicitly authorized for the target, workspace, and repository.
- `target_ambiguous`: automatic selection has more than one eligible target.

A manual override returns the same incapable, unavailable, or unauthorized result as automatic selection because it passes through the same policy.

## Ownership Boundary

Logres contains no Amp or Orb client. The package accepts provider observations without credentials and returns deterministic selection results. Burdgeon must supply the authenticated provider adapter and persistence implementation. Until Amp exposes or Burdgeon possesses a factual Orb inventory and state source, the host must show target discovery as unavailable rather than synthesize an inventory.
