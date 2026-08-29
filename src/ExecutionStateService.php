<?php

declare(strict_types=1);

namespace Sifrious\Logres;

use DateTimeImmutable;
use RuntimeException;

final readonly class ExecutionStateService
{
    public function __construct(private ExecutionStateStore $store) {}

    public function acquireLease(RunId $runId, AttemptId $attemptId, LeaseId $leaseId, ExecutionNodeRef $holder, LeaseToken $token, string $acquisitionId, DateTimeImmutable $now, int $ttlSeconds): ExecutionLease
    {
        $current = $this->requireState($runId);
        $existing = $current->currentAttempt()?->leaseByAcquisition($acquisitionId);
        if ($existing !== null) {
            return $current->acquireLease($attemptId, $leaseId, $holder, $token, $acquisitionId, $now, $ttlSeconds)->currentAttempt()->leaseByAcquisition($acquisitionId);
        }

        $next = $current->acquireLease($attemptId, $leaseId, $holder, $token, $acquisitionId, $now, $ttlSeconds);
        if (! $this->store->compareAndSwap($runId, $current->version, $next)) {
            $winner = $this->requireState($runId)->currentAttempt()?->leaseByAcquisition($acquisitionId);
            if ($winner !== null && $winner->holder == $holder && hash_equals($winner->token->value, $token->value)) {
                return $winner;
            }
            throw ExecutionStateRejected::because(ExecutionStateRejectionReason::AlreadyLeased, 'Another contender changed the Attempt before this Lease could be acquired.');
        }

        return $next->currentAttempt()->leaseByAcquisition($acquisitionId) ?? throw new RuntimeException('Acquired Lease is missing from the resulting state.');
    }

    public function save(ExecutionState $previous, ExecutionState $next): void
    {
        if (! $this->store->compareAndSwap($previous->runId, $previous->version, $next)) {
            throw ExecutionStateRejected::because(ExecutionStateRejectionReason::StaleState, 'Execution state changed concurrently.');
        }
    }

    private function requireState(RunId $runId): ExecutionState
    {
        return $this->store->find($runId) ?? throw new RuntimeException("Execution state {$runId->value} was not found.");
    }
}
