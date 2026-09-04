# Agent-to-agent delegation contract

Status: contract proposal and deterministic fixture only. MME-1010 policy
primitives are available on main. Bind their determinations to the delegation
operation with the MME-1007 runtime adapter. NeedsInput storage/resume wiring
follows MME-1299.

## Boundary

Delegation is one Logres capability: `DelegateRun::delegate`. It records a
durable edge from the currently executing parent `RunId` and `AttemptId` to a
new child `RunId` and immutable child `ExecutionRequestId`. The child executes
through the canonical Logres execution state and attempt machinery. An agent
definition never calls another agent definition directly, and this package
does not add a nested execution loop.

`AgentDefinitionRef` identifies the Orbis definition through the shared
`CrossPackageReference` contract (`sifrious/orbis`, `agent-definition`) and
freezes its object version and content hash. Logres does not parse or own Orbis
agent manifests.

The host implementation must atomically persist:

1. the `DelegationRequest`;
2. the child execution request and `Run`;
3. the initial child `ExecutionState`; and
4. the MME-1007 re-entry determination that schedules the child.

Replaying the same operation identity with identical bytes is idempotent.
Reusing a delegation identity, operation identity, or child Run identity with
different bytes is a conflict. A crash before the atomic commit creates no
child. A crash after commit re-enters MME-1007 from durable state.

## Authority and policy

`DelegationContext::boundedBy` copies repository, workspace authority,
environment, and runtime from the parent's frozen
`DispatchAuthorizationSnapshot`. The child path must remain contained by the
parent path, and child permissions must be a non-empty subset. A child receives
its own current dispatch grant before dispatch; parent authority is only an
upper bound and is not itself reusable as child authority.

`DelegationAuthorization` freezes the allowed decision produced from the
MME-1010 `LoopPolicy` and `LoopPolicyDetermination`, including policy version,
resulting depth, active-child count, and finite depth and concurrency limits.
It intentionally does not reevaluate policy. The MME-1007 integration must
persist the applicable determination and account for child creation in the
same atomic step transition.

## Observation and propagation

`DelegationReadModel` combines the immutable parent-child edge with the
canonical child `ExecutionStateReadModel`. Child attempts are therefore the
same `ExecutionAttempt` records used by every Logres run; delegation has no
parallel attempt model.

Waiting on one child is a parent MME-1007 determination, not a synchronous
call. Multiple children are independent persisted edges and may progress in
parallel only while MME-1010 permits it. Child completion, failure,
cancellation, and timeout are observed from canonical child state and become
inputs to a later parent determination; they never mutate the parent directly.

While a child is in `needs_input`, the projection must include the durable
`InputRequestReference` created by MME-1299. Resuming that child advances its
canonical execution state without repeating completed parent or child steps.

## Run-tree query

`DelegationStore::childrenOf` and `parentOf` expose inspectable parent-child
provenance. A host builds a run tree by traversing these immutable edges and
joining each child to its canonical execution-state projection. Tree reads do
not infer lineage from provider metadata.

The deterministic proposal fixture is
[`fixtures/delegation-contract-v1.json`](fixtures/delegation-contract-v1.json).
It covers two concurrent children, a NeedsInput observation, failure
propagation input, bounded context, policy provenance, and canonical Attempt
identity.
