<?php

declare(strict_types=1);

namespace Sifrious\Logres;

use DateTimeImmutable;
use InvalidArgumentException;

final readonly class ExecutionState
{
    /** @param list<ExecutionAttempt> $attempts */
    public function __construct(
        public RunId $runId,
        public RunStatus $status,
        public DateTimeImmutable $createdAt,
        public ?DateTimeImmutable $scheduledAt = null,
        public ?DateTimeImmutable $startedAt = null,
        public ?DateTimeImmutable $finishedAt = null,
        public ?AttemptId $activeAttemptId = null,
        public array $attempts = [],
        public ?string $failureReason = null,
        public ?string $terminalResultReference = null,
        public int $version = 0,
    ) {
        if ($version < 0) {
            throw new InvalidArgumentException('Execution-state version cannot be negative.');
        }
        if ($status->isTerminal() && ($finishedAt === null || $activeAttemptId !== null)) {
            throw new InvalidArgumentException('A terminal Run requires a finish time and cannot retain an active Attempt.');
        }
        foreach ($attempts as $attempt) {
            if ($attempt->runId->value !== $runId->value) {
                throw new InvalidArgumentException('Every Attempt must belong to this Run.');
            }
        }
    }

    public static function create(RunId $runId, DateTimeImmutable $createdAt): self
    {
        return new self($runId, RunStatus::Pending, $createdAt);
    }

    public function scheduleAttempt(AttemptId $attemptId, DateTimeImmutable $now): self
    {
        if ($this->status->isTerminal()) {
            $this->reject(ExecutionStateRejectionReason::AlreadyTerminal, 'A terminal Run cannot create another Attempt.');
        }
        if ($this->activeAttemptId !== null) {
            $this->reject(ExecutionStateRejectionReason::InvalidTransition, 'The current Attempt must finish before another Attempt is created.');
        }

        $previous = $this->attempts === [] ? null : $this->attempts[array_key_last($this->attempts)]->id;
        $attempt = new ExecutionAttempt($attemptId, $this->runId, count($this->attempts) + 1, AttemptStatus::Ready, $now, $previous);
        $status = $this->status === RunStatus::Pending || $this->status === RunStatus::NeedsInput ? RunStatus::Preparing : $this->status;
        RunTransitionPolicy::assertAllowed($this->status, $status);

        return $this->copy(status: $status, scheduledAt: $now, activeAttemptId: $attemptId, attempts: [...$this->attempts, $attempt]);
    }

    public function acquireLease(AttemptId $attemptId, LeaseId $leaseId, ExecutionNodeRef $holder, LeaseToken $token, string $acquisitionId, DateTimeImmutable $now, int $ttlSeconds): self
    {
        $attempt = $this->requireCurrentAttempt($attemptId);
        if ($existing = $attempt->leaseByAcquisition($acquisitionId)) {
            if ($existing->holder == $holder && hash_equals($existing->token->value, $token->value)) {
                return $this;
            }
            $this->reject(ExecutionStateRejectionReason::ForeignLease, 'An acquisition identity cannot be reused with different authority.');
        }
        if ($this->status->isTerminal() || $attempt->status->isTerminal()) {
            $this->reject(ExecutionStateRejectionReason::NotEligibleForLease, 'Terminal Runs and Attempts cannot be leased.');
        }
        if ($attempt->status !== AttemptStatus::Ready && $attempt->status !== AttemptStatus::Expired) {
            $this->reject(ExecutionStateRejectionReason::AlreadyLeased, 'The Attempt is not currently reclaimable.');
        }
        if ($attempt->activeLease()?->isActiveAt($now)) {
            $this->reject(ExecutionStateRejectionReason::AlreadyLeased, 'The Attempt already has an active Lease.');
        }
        if ($ttlSeconds < 1 || trim($acquisitionId) === '') {
            throw new InvalidArgumentException('Acquisition identity and positive TTL are required.');
        }

        $lease = new ExecutionLease($leaseId, $attemptId, $holder, $token, $acquisitionId, LeaseStatus::Active, $now, $now->modify("+{$ttlSeconds} seconds"));
        $next = new ExecutionAttempt($attempt->id, $attempt->runId, $attempt->number, AttemptStatus::Leased, $attempt->createdAt, $attempt->previousAttemptId, [...$attempt->leases, $lease], $attempt->startedAt, $attempt->finishedAt, $attempt->failureReason);

        return $this->replaceAttempt($next);
    }

    public function start(AttemptId $attemptId, LeaseToken $token, DateTimeImmutable $now): self
    {
        $attempt = $this->requireCurrentAttempt($attemptId);
        $lease = $this->requireAuthorizedActiveLease($attempt, $token, $now);
        if ($attempt->status === AttemptStatus::Running) {
            return $this;
        }
        if ($attempt->status !== AttemptStatus::Leased) {
            $this->reject(ExecutionStateRejectionReason::InvalidTransition, 'Only a leased Attempt can start.');
        }
        RunTransitionPolicy::assertAllowed($this->status, RunStatus::Running);
        $next = new ExecutionAttempt($attempt->id, $attempt->runId, $attempt->number, AttemptStatus::Running, $attempt->createdAt, $attempt->previousAttemptId, $attempt->leases, $now);

        return $this->replaceAttempt($next)->copy(status: RunStatus::Running, startedAt: $this->startedAt ?? $now);
    }

    public function renewLease(AttemptId $attemptId, ExecutionNodeRef $holder, LeaseToken $token, string $renewalId, DateTimeImmutable $now, int $ttlSeconds): self
    {
        $attempt = $this->requireCurrentAttempt($attemptId);
        $lease = $attempt->activeLease() ?? $this->reject(ExecutionStateRejectionReason::LeaseExpired, 'The Attempt has no active Lease.');
        $renewed = $lease->renew($holder, $token, $renewalId, $now, $ttlSeconds);
        if ($renewed === $lease) {
            return $this;
        }
        return $this->replaceLease($attempt, $renewed);
    }

    public function releaseLease(AttemptId $attemptId, ExecutionNodeRef $holder, LeaseToken $token, string $releaseId, DateTimeImmutable $now): self
    {
        $attempt = $this->requireCurrentAttempt($attemptId);
        $lease = $attempt->activeLease() ?? $this->findReleaseReplay($attempt, $releaseId);
        $released = $lease->release($holder, $token, $releaseId, $now);
        if ($released === $lease) {
            return $this;
        }
        $status = $attempt->status === AttemptStatus::Leased ? AttemptStatus::Ready : $attempt->status;
        return $this->replaceLease($attempt, $released, $status);
    }

    public function expireLease(AttemptId $attemptId, DateTimeImmutable $now): self
    {
        $attempt = $this->requireCurrentAttempt($attemptId);
        $lease = $attempt->activeLease();
        if ($lease === null) {
            if ($attempt->status === AttemptStatus::Expired) {
                return $this;
            }
            $this->reject(ExecutionStateRejectionReason::LeaseExpired, 'The Attempt has no active Lease.');
        }
        return $this->replaceLease($attempt, $lease->expire($now), AttemptStatus::Expired);
    }

    public function nextAttemptAfterExpiry(AttemptId $attemptId, AttemptId $nextAttemptId, DateTimeImmutable $now): self
    {
        $attempt = $this->requireCurrentAttempt($attemptId);
        if ($attempt->status !== AttemptStatus::Expired || $this->status->isTerminal()) {
            $this->reject(ExecutionStateRejectionReason::NotEligibleForLease, 'Only an expired Attempt on a non-terminal Run can be followed by another Attempt.');
        }
        $next = new ExecutionAttempt($nextAttemptId, $this->runId, $attempt->number + 1, AttemptStatus::Ready, $now, $attempt->id);

        return $this->copy(activeAttemptId: $nextAttemptId, attempts: [...$this->attempts, $next], scheduledAt: $now);
    }

    public function finish(AttemptId $attemptId, LeaseToken $token, RunStatus $terminalStatus, DateTimeImmutable $now, ?string $reason = null, ?string $resultReference = null): self
    {
        if (! $terminalStatus->isTerminal()) {
            $this->reject(ExecutionStateRejectionReason::InvalidTransition, 'A finishing status must be terminal.');
        }
        if ($this->status->isTerminal()) {
            if ($this->status === $terminalStatus && $this->failureReason === $reason && $this->terminalResultReference === $resultReference) {
                return $this;
            }
            $this->reject(ExecutionStateRejectionReason::AlreadyTerminal, 'A terminal Run cannot be reopened or replaced.');
        }
        $attempt = $this->requireCurrentAttempt($attemptId);
        $lease = $this->requireAuthorizedActiveLease($attempt, $token, $now);
        RunTransitionPolicy::assertAllowed($this->status, $terminalStatus);
        $released = $lease->release($lease->holder, $lease->token, 'terminal:'.$terminalStatus->value, $now);
        $attemptStatus = $terminalStatus === RunStatus::Succeeded ? AttemptStatus::Succeeded : AttemptStatus::Failed;
        $nextAttempt = $this->replaceLeaseValue($attempt, $released, $attemptStatus, $now, $reason);

        return $this->replaceAttempt($nextAttempt)->copy(status: $terminalStatus, finishedAt: $now, activeAttemptId: null, failureReason: $reason, terminalResultReference: $resultReference);
    }

    public function currentAttempt(): ?ExecutionAttempt
    {
        if ($this->activeAttemptId === null) {
            return null;
        }
        foreach ($this->attempts as $attempt) {
            if ($attempt->id->value === $this->activeAttemptId->value) {
                return $attempt;
            }
        }
        return null;
    }

    private function requireCurrentAttempt(AttemptId $id): ExecutionAttempt
    {
        $attempt = $this->currentAttempt();
        if ($attempt === null || $attempt->id->value !== $id->value) {
            $this->reject(ExecutionStateRejectionReason::StaleAttempt, 'The command references a stale or foreign Attempt.');
        }
        return $attempt;
    }

    private function requireAuthorizedActiveLease(ExecutionAttempt $attempt, LeaseToken $token, DateTimeImmutable $now): ExecutionLease
    {
        $lease = $attempt->activeLease() ?? $this->reject(ExecutionStateRejectionReason::LeaseExpired, 'The Attempt has no active Lease.');
        if (! hash_equals($lease->token->value, $token->value)) {
            $this->reject(ExecutionStateRejectionReason::ForeignLease, 'The Lease token is foreign or stale.');
        }
        if (! $lease->isActiveAt($now)) {
            $this->reject(ExecutionStateRejectionReason::LeaseExpired, 'The Lease has expired.');
        }
        return $lease;
    }

    private function findReleaseReplay(ExecutionAttempt $attempt, string $releaseId): ExecutionLease
    {
        foreach (array_reverse($attempt->leases) as $lease) {
            if ($lease->releaseId === $releaseId) {
                return $lease;
            }
        }
        return $this->reject(ExecutionStateRejectionReason::LeaseExpired, 'The Attempt has no active Lease.');
    }

    private function replaceLease(ExecutionAttempt $attempt, ExecutionLease $replacement, ?AttemptStatus $status = null): self
    {
        $leases = array_map(static fn (ExecutionLease $lease): ExecutionLease => $lease->id->value === $replacement->id->value ? $replacement : $lease, $attempt->leases);
        return $this->replaceAttempt(new ExecutionAttempt($attempt->id, $attempt->runId, $attempt->number, $status ?? $attempt->status, $attempt->createdAt, $attempt->previousAttemptId, $leases, $attempt->startedAt, $attempt->finishedAt, $attempt->failureReason));
    }

    private function replaceLeaseValue(ExecutionAttempt $attempt, ExecutionLease $replacement, AttemptStatus $status, DateTimeImmutable $finishedAt, ?string $reason): ExecutionAttempt
    {
        $leases = array_map(static fn (ExecutionLease $lease): ExecutionLease => $lease->id->value === $replacement->id->value ? $replacement : $lease, $attempt->leases);
        return new ExecutionAttempt($attempt->id, $attempt->runId, $attempt->number, $status, $attempt->createdAt, $attempt->previousAttemptId, $leases, $attempt->startedAt, $finishedAt, $reason);
    }

    private function replaceAttempt(ExecutionAttempt $replacement): self
    {
        $attempts = array_map(static fn (ExecutionAttempt $attempt): ExecutionAttempt => $attempt->id->value === $replacement->id->value ? $replacement : $attempt, $this->attempts);
        return $this->copy(attempts: $attempts);
    }

    /** @param list<ExecutionAttempt>|null $attempts */
    private function copy(?RunStatus $status = null, ?DateTimeImmutable $scheduledAt = null, ?DateTimeImmutable $startedAt = null, ?DateTimeImmutable $finishedAt = null, AttemptId|false|null $activeAttemptId = false, ?array $attempts = null, ?string $failureReason = null, ?string $terminalResultReference = null): self
    {
        return new self($this->runId, $status ?? $this->status, $this->createdAt, $scheduledAt ?? $this->scheduledAt, $startedAt ?? $this->startedAt, $finishedAt ?? $this->finishedAt, $activeAttemptId === false ? $this->activeAttemptId : $activeAttemptId, $attempts ?? $this->attempts, $failureReason ?? $this->failureReason, $terminalResultReference ?? $this->terminalResultReference, $this->version + 1);
    }

    /** @return never */
    private function reject(ExecutionStateRejectionReason $reason, string $message): never
    {
        throw ExecutionStateRejected::because($reason, $message);
    }
}
