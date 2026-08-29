# Runner boundary

Logres defines the canonical, provider-neutral description of a runner. A runner is an execution node that can be discovered and evaluated before dispatch without importing a provider SDK or a host framework.

## Contracts

- `RunnerIdentity` provides a stable identity in the `runner:` namespace and maps to the existing `ExecutionNodeRef` lease-holder identity.
- `PlatformIdentity` records the operating system and architecture reported by the host.
- `CapabilitySnapshot` records normalized capabilities, Wardrobe runtime-adapter references, supported protocol versions, and the observation time.
- `RunnerAvailability` distinguishes available, busy, draining, and offline runners.
- `CurrentWorkload` records active work against positive capacity.
- `RunnerDescriptor` combines those observations with stable authorization-grant references.

Arrays in a capability snapshot are non-empty, de-duplicated, and sorted so equivalent observations have deterministic values. The snapshot is evidence observed at a point in time; it does not grant dispatch authority.

## Ownership

Logres owns the compatibility contracts and their validation. Burdgeon owns discovery, heartbeat integration, persistence, configuration, and pre-dispatch use of runner observations. Wardrobe owns runtime adapter profiles; descriptors reference adapter identities rather than duplicating their definitions. Stacks supplies stable workspace and grant identities.

The boundary deliberately contains no HTTP, queue, UI, framework, provider-SDK, or process-launching dependency.
