# Durable agent Step loop

`AgentStepLoop` is the framework-neutral kernel called by one queued job. It executes one bounded cycle:

```text
observe → determine → persist → act or reconcile → record → re-enter
```

The host owns Laravel jobs, Eloquent transactions, clocks, delayed delivery, and concrete model/tool/provider effects. Logres owns the orchestration contract and continues to use `ExecutionState` as the only Run/Attempt/Lease lifecycle. The Step log is an inspectable decision and observation history, not another execution state machine.

## Identity and persistence

Every Step has a deterministic `AgentStepId` derived from Run identity, explicit Attempt identity, and monotonically increasing sequence. Every effect has a deterministic operation identity derived from that Step and action. `AgentStepStore::reserve` is the host transaction boundary: it must atomically validate the expected `ExecutionState` version and reserve Run/sequence, Step, and operation identities before an effect.

An identical reservation or observation replay converges. Conflicting identity reuse fails. A stale state version returns no reservation; the loop schedules a reread instead of applying a stale decision.

`AgentStepEffect::reconcileOrPerform` must first reconcile its stable operation identity or atomically fence that identity before crossing an external boundary. Existing `ProviderInvocationCoordinator`, `ExecutionRunner`, and `ExecutionStateService` provide the corresponding provider, runner, and lifecycle semantics. Redelivery may call the port again, but cannot authorize a second logical effect.

## Policy, input, and provenance

The loop consumes the versioned `LoopPolicy` contract. Its determiner records `LoopPolicyOutcome`, the exact `LoopPolicyClause`, `LoopBudgetRemaining`, rationale, input facts, and canonical `EvidenceReference` values before acting. Limits are never hard-coded in the loop.

`needs_input` remains canonical `ExecutionState` lifecycle. A waiting determination has a durable re-entry time governed by policy and performs no external effect. The MME-1299 input transition owns question/response/resume semantics; the loop only re-enters the same Step/Attempt lineage after that state changes.

Step records refer to canonical Run identity and do not copy workspace/revision fields. Consumers resolve immutable execution provenance through the Run contract owned by MME-1818, preventing a second provenance schema.

## Re-entry contract

After an observation is durable, a non-terminal Run receives one idempotent re-entry request. A terminal `ExecutionState` never receives re-entry. The queue adapter must durably accept `AgentStepReentry::schedule` before acknowledging the current job. If a worker dies first, redelivery resumes the persisted determination or observation.

## Deterministic conformance matrix

| Determination | Bounded effect | Required durable result | Re-entry |
|---|---|---|---|
| `wait` | none | policy clause, remaining budget, wait deadline | delayed if Run remains non-terminal |
| `lease` | canonical Attempt lease acquisition | lease observation or rejection evidence | immediate |
| `invoke` | one idempotently fenced model/tool/provider invocation | output as observation, never trusted completion | immediate |
| `reconcile` | one provider/effect lookup | found, absent, or uncertain evidence | immediate or policy-delayed |
| `retry` | canonical linked Attempt transition | explicit previous/new Attempt lineage | immediate |
| `complete` | canonical verification/finalization transition | independently verified terminal state | never after terminal |
| `escalate` | canonical policy-owned escalation transition | structured reason and evidence | never after terminal |
| `stop` | canonical policy-owned terminal transition | structured reason and evidence | never after terminal |

| Crash boundary | Durable state on redelivery | Required behavior |
|---|---|---|
| before determination reserve | no Step | reread and determine |
| after reserve, before effect | determination only | reconcile or perform the same operation identity |
| during effect | determination only | reconcile uncertainty; never issue an unfenced duplicate |
| after effect, before observation record | determination only | recover the same operation observation |
| after observation, before queue schedule | complete Step | idempotently schedule the next Step |
| after queue schedule, before job acknowledgement | complete Step plus scheduled identity | duplicate schedule and job delivery converge |
| optimistic-concurrency loss | no stale reservation | reread canonical state and re-enter |
| terminal redelivery | recorded terminal lifecycle | return history without executing or scheduling work |
