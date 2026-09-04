<?php

declare(strict_types=1);

namespace Sifrious\Logres;

use DateTimeImmutable;

interface AgentStepReentry
{
    /**
     * Idempotently enqueue or schedule one future queued job. The identity must
     * be durably accepted before returning.
     */
    public function schedule(
        RunId $runId,
        AgentStepId $stepId,
        DateTimeImmutable $notBefore,
        string $idempotencyIdentity,
    ): void;
}
