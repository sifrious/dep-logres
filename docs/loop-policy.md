# Loop policy and termination

`LoopPolicy` is the persisted, versioned policy input for an orchestration loop. The
host chooses finite limits and an absolute UTC deadline before execution starts;
this package does not embed product defaults in an orchestrator. A consumer such
as MME-1007 persists the complete `LoopPolicy::toArray()` value with the attempt
and supplies current observations to `LoopPolicyEvaluator`.

The policy bounds steps, completed attempts, tool calls, consecutive failures,
wall-clock time, provider tokens and cost, delegation depth, concurrent children,
and human-input wait time. Token and cost remainder is `null` when the provider
does not expose that measurement; the evaluator never invents usage. Attempt,
tool, delegation, and child limits apply when the corresponding `LoopOperation`
is requested, allowing an active attempt to finish without treating its already
started identity as a retry.

## Determinations

Every evaluation returns a `LoopPolicyDetermination` containing policy name and
version, the exact clause that controlled the result, all exhausted clauses,
remaining budgets, observation time, a structured outcome, and canonical
`EvidenceReference` values. The outcomes are:

- `continue` and `awaiting_input` for non-terminal work;
- `successful_completion` only after required independent verification;
- `cancelled` and `authorization_revoked` for explicit external observations;
- `unresolved_needs_input` when bounded human wait expires or required
  verification is unavailable; and
- `policy_exhausted` when a finite budget or wall-clock deadline is reached.

Cancellation, authorization revocation, and timeout are observations evaluated
by policy. Reaching a synchronous deadline does not revoke a Lease or imply that
uncertain remote work was destroyed; the MME-1807 execution-state commands still
own cancellation confirmation, reconciliation, Attempt lineage, and Lease
authority. Authorization activity is likewise supplied from the MME-2104 grant
semantics rather than inferred from target capability.

Limits use stop-before-next-operation semantics: a counter one below its maximum
allows the operation and a counter exactly at its maximum exhausts it. Completion
is evaluated before exhaustion so already verified work succeeds at its boundary.
Cancellation and authorization revocation take precedence over completion.

Terminal determinations are monotonic. Passing a terminal determination back to
the evaluator returns that exact value; changing policy name or version during
an attempt is rejected. Continuing later therefore requires the existing
execution-state model's new linked Attempt, not reopening or replacing a
terminal attempt.

This package deliberately reuses `EvidenceReference`,
`RequiredVerificationOutcome`, and existing Run/Attempt/authorization semantics.
It defines no parallel provenance or identity schema.
