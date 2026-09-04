<?php

declare(strict_types=1);

namespace Sifrious\Logres;

use DateTimeImmutable;
use InvalidArgumentException;

final readonly class ExecutionAttempt
{
    /** @param list<ExecutionLease> $leases */
    public function __construct(
        public AttemptId $id,
        public RunId $runId,
        public int $number,
        public AttemptStatus $status,
        public DateTimeImmutable $createdAt,
        public ?AttemptId $previousAttemptId = null,
        public array $leases = [],
        public ?DateTimeImmutable $startedAt = null,
        public ?DateTimeImmutable $finishedAt = null,
        public ?string $failureReason = null,
        public ?StacksExecutionContext $executionIdentity = null,
    ) {
        if ($number < 1 || ($number === 1) === ($previousAttemptId !== null)) {
            throw new InvalidArgumentException('Attempt lineage must begin at one and every subsequent Attempt must name its predecessor.');
        }
        $active = array_filter($leases, static fn (ExecutionLease $lease): bool => $lease->status === LeaseStatus::Active);
        if (count($active) > 1) {
            throw new InvalidArgumentException('An Attempt cannot contain more than one active Lease.');
        }
        foreach ($leases as $lease) {
            if ($executionIdentity !== null && $lease->executionIdentity?->canonicalIdentity() !== $executionIdentity->canonicalIdentity()) {
                throw new InvalidArgumentException('Every Lease must preserve its Attempt execution identity.');
            }
        }
    }

    public function activeLease(): ?ExecutionLease
    {
        foreach (array_reverse($this->leases) as $lease) {
            if ($lease->status === LeaseStatus::Active) {
                return $lease;
            }
        }
        return null;
    }

    public function leaseByAcquisition(string $acquisitionId): ?ExecutionLease
    {
        foreach ($this->leases as $lease) {
            if ($lease->acquisitionId === $acquisitionId) {
                return $lease;
            }
        }
        return null;
    }
}
