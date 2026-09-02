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

## Terminology and policy

- **execution target** — one provider-neutral destination frozen for a task/run version.
- **candidate** — an immutable provider or runner observation offered to Logres policy.
- **capability snapshot** — a versioned observed set of runtime/tool abilities; staleness is independent of availability.
- **execution class** — `local`, `managed-cloud`, `customer-owned`, or `provider-hosted` placement.
- **automatic target** — the deterministic winner before any override.
- **effective target** — the eligible target actually frozen for dispatch.
- **override** — a requested preference that records actor, reason, authorization decision, and timestamp while passing the same eligibility policy.

Policy `execution-target-v2` sorts eligible candidates by an explicit preferred target, execution-class order, and stable target identity. Identical frozen inputs therefore produce the same result regardless of provider inventory order. Duplicate provider identities make every duplicate ineligible and fail closed.

Local-only or unpushed work is represented by allowing only the `local` execution class. Customer-infrastructure-only work allows only `customer-owned`; it cannot fall back to managed compute. Git-backed work may allow managed compute explicitly. The host maps repository and authorization facts into these requirements; the selector never infers authority from an ambient checkout.

Machine eligibility precedes Wardrobe runtime/provider selection. The legacy `agentAdapter` field remains in the requirements snapshot for compatibility and provenance, but it does not choose Codex, Claude, Amp, or another provider during machine selection.

## Persisted provenance

`ExecutionTargetSelection` freezes requirements, policy version, selection timestamp, every candidate and policy check, stable rejection codes, rankings, capability snapshot versions, automatic and effective targets, alternates, selection explanation, tie-break explanation, and override provenance. A host must persist this value before dispatch. Provider acknowledgement must reconcile its destination with the frozen effective target; it may not silently substitute another target.

## Rejected alternatives

Logres rejects hidden default targets, “first provider result wins,” controller-owned policy, UI-owned eligibility, mutable inventory as historical provenance, arbitrary caller-supplied target authority, provider-specific branches, and fallback to another execution class after policy failure.

## Human verification

From a clean checkout run `composer check`. `ExecutionTargetSelectorTest` covers deterministic reordered inventories, local/managed/customer placement constraints, duplicate provider identities, stable candidate rejection reasons, manual override provenance, capability snapshot versions, immutable selection/read models, and absent inventory. Live provider discovery and application persistence remain Burdgeon integration responsibilities.

## Ownership Boundary

Logres contains no Amp or Orb client. The package accepts provider observations without credentials and returns deterministic selection results. Burdgeon must supply the authenticated provider adapter and persistence implementation. Until Amp exposes or Burdgeon possesses a factual Orb inventory and state source, the host must show target discovery as unavailable rather than synthesize an inventory.
