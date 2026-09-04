# Loop domain composition

`LoopComposition` is a provider-neutral, side-effect-free projection over existing
domain objects. It does not introduce a Loop database identity or a second
lifecycle. The immutable execution request and its original-input reference are
the causal root.

The composition joins:

- Elwin-owned consequential decisions through `LoopCheckpoint` and
  `LoopInterventionReference`;
- the existing plan and exact task values through `TaskPlan` and
  `TranslatedTask`;
- Orbis-owned immutable handoffs through `LoopHandoffReference`;
- adapter-owned work-item mappings through `LoopExternalWorkReference`;
- Logres-owned current execution and normalized results through `Run` and
  `RunResult`; and
- independently observed verification and evidence through `VerifiedOutcome`.

There is no MCP schema or serialization contract here. MME-2272 adapters project
this domain value without becoming authoritative for it.

## Materialization gate

A materialized plan is valid only after one architecture-placement checkpoint
followed by one simplicity/scope-cut checkpoint. Both decision references remain
in the composition. A deliberate zero-work outcome carries its durable decision
and creates no empty plan, task, handoff, external ticket, or Run.

Phase handoffs retain the originating plan reference. Ticket handoffs retain the
originating task and derive a stable content identity. An external provider
mapping records both explicit write authorization and an idempotency identity;
the composition never performs the write.

## Determination

The composition derives only cross-object disposition:

- explicit owner determinations can clarify, escalate, or stop;
- failed execution or required verification reworks the owning task;
- unavailable required verification escalates;
- cancellation stops;
- tasks still missing a terminal result, independent verification, or observed
  evidence advance but cannot complete; and
- completion requires every non-skipped task to have verified evidence and a
  finalized successful result.

Task dependency order remains the plan's explicit graph. Composition array order
is not treated as execution order. Re-entry returns the owning task and existing
Run reference; Run/Attempt mutation, retries, leases, and terminal semantics
remain in the Logres lifecycle aggregate. Stacks remains authoritative for
repository/workspace identity, Wardrobe for invocation, Elwin for clarification,
and Funes for durable evidence/history.
