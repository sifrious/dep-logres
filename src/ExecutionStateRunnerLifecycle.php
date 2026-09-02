<?php

declare(strict_types=1);

namespace Sifrious\Logres;

use DateTimeImmutable;

/** Runner-facing view of the canonical MME-1807 Run/Attempt/Lease lifecycle. */
final readonly class ExecutionStateRunnerLifecycle implements RunnerLifecycle
{
    public function __construct(private ExecutionStateStore $states) {}

    public function permits(ExecutionEnvelope $envelope, RunnerIdentity $runner, DateTimeImmutable $now): bool
    {
        $state = $this->states->find($envelope->runId);
        $attempt = $state?->currentAttempt();
        $lease = $attempt?->activeLease();

        if ($state === null || $attempt === null || $lease === null) {
            return false;
        }

        return ! $state->status->isTerminal()
            && $attempt->id->value === $envelope->attemptId->value
            && $lease->id->value === $envelope->leaseId->value
            && $lease->holder->value === $runner->value
            && hash_equals($lease->token->value, $envelope->leaseToken->value)
            && $lease->isActiveAt($now);
    }

    public function cancellationRequested(ExecutionEnvelope $envelope): bool
    {
        return $this->states->find($envelope->runId)?->status === RunStatus::Cancelled;
    }
}
