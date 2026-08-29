# Execution Targets

Every dispatched task must refer to a concrete, persisted target snapshot. A target records its provider-qualified identity, observed availability and health, runtime, workspace authority, repository identity, supported agent adapters and capabilities, current task, and observation time.

## Workflow

1. The host maps a canonical task to `ExecutionTargetRequirements`.
2. A provider adapter returns factual `ExecutionTargetCandidate` values through `ExecutionTargetCatalog`.
3. `ExecutionTargetSelector` evaluates every candidate by provider, workspace authority, repository identity, execution class, capabilities, operational state, snapshot freshness, resource availability, and explicit authorization. Runtime-adapter selection remains a later Wardrobe concern.
4. Eligible candidates are sorted by requested preference, execution-class policy order, and stable target identity. The selector never depends on inventory order.
5. A decision freezes every candidate evaluation, its rejection reasons and policy checks, the policy version, the automatic winner, any validated override, and the effective target.
6. The host persists the immutable `ExecutionTargetSelection` through `ExecutionTargetStore` before dispatch.
7. Hosts render `ExecutionTargetReadModel` and `ExecutionTargetCatalogReadModel`; they do not recompute package rules.

No eligible candidate returns `NeedsTarget` with either `NO_TARGETS_DISCOVERED` or `NO_ELIGIBLE_TARGET`. Candidate-level failures retain specific codes such as `TARGET_STALE`, `TARGET_UNAUTHORIZED`, `TARGET_CAPABILITY_MISMATCH`, and `TARGET_RESOURCE_EXHAUSTED`. No fallback is inferred.

## Outcomes

- `Selected`: the persisted decision contains the automatic and effective target plus the full audit.
- `NeedsTarget`: no candidate passes every check; dispatch must not begin.
- `Rejected`: a requested override is unknown or ineligible and fails closed.

A manual override passes through the same checks as automatic selection. It never removes the automatic winner from history.

## Ownership Boundary

Logres contains no Amp or Orb client. The package accepts provider observations without credentials and returns deterministic selection results. Burdgeon must supply the authenticated provider adapter and persistence implementation. Until Amp exposes or Burdgeon possesses a factual Orb inventory and state source, the host must show target discovery as unavailable rather than synthesize an inventory.
