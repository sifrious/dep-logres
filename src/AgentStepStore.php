<?php

declare(strict_types=1);

namespace Sifrious\Logres;

interface AgentStepStore
{
    public function find(AgentStepId $stepId): ?AgentStepRecord;

    /** @return list<AgentStepRecord> Ordered by determination sequence. */
    public function history(RunId $runId): array;

    /**
     * Atomically reserve the Run/sequence, Step identity, and operation identity
     * only when the canonical ExecutionState still has the determination's
     * expectedStateVersion. This is the host transaction boundary.
     *
     * Returns null on an optimistic-concurrency loss. An identical replay
     * returns the existing record; conflicting identity reuse must throw.
     */
    public function reserve(AgentStepDetermination $determination): ?AgentStepRecord;

    /**
     * Durably records an observation before returning. Identical replay
     * converges; conflicting operation evidence must throw.
     */
    public function record(AgentStepObservation $observation): AgentStepRecord;
}
