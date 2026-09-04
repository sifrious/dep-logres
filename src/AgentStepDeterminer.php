<?php

declare(strict_types=1);

namespace Sifrious\Logres;

use DateTimeImmutable;

interface AgentStepDeterminer
{
    /**
     * Determines exactly one action from durable canonical state, persisted
     * policy, and ordered Step history. It must not perform external effects.
     *
     * @param list<AgentStepRecord> $history
     */
    public function determine(
        AgentStepId $stepId,
        ExecutionState $state,
        LoopPolicy $policy,
        array $history,
        DateTimeImmutable $observedAt,
    ): AgentStepDetermination;
}
