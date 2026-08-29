<?php

declare(strict_types=1);

namespace Sifrious\Logres;

use DateTimeImmutable;
use InvalidArgumentException;

final readonly class ExecutionLease
{
    public function __construct(
        public LeaseId $id,
        public AttemptId $attemptId,
        public ExecutionNodeRef $holder,
        public LeaseToken $token,
        public string $acquisitionId,
        public LeaseStatus $status,
        public DateTimeImmutable $acquiredAt,
        public DateTimeImmutable $expiresAt,
        public ?DateTimeImmutable $renewedAt = null,
        public ?DateTimeImmutable $releasedAt = null,
        public ?string $lastRenewalId = null,
        public ?string $releaseId = null,
    ) {
        if (trim($acquisitionId) === '' || $expiresAt <= $acquiredAt) {
            throw new InvalidArgumentException('A Lease requires an acquisition identity and future expiry.');
        }
        if ($status === LeaseStatus::Released && $releasedAt === null) {
            throw new InvalidArgumentException('A released Lease requires its release timestamp.');
        }
    }

    public function renew(ExecutionNodeRef $holder, LeaseToken $token, string $renewalId, DateTimeImmutable $now, int $ttlSeconds): self
    {
        $this->assertAuthority($holder, $token);
        if ($this->lastRenewalId === $renewalId) {
            return $this;
        }
        if ($this->status !== LeaseStatus::Active || $now >= $this->expiresAt) {
            throw ExecutionStateRejected::because(ExecutionStateRejectionReason::LeaseExpired, 'Only an active, unexpired Lease can be renewed.');
        }
        if ($ttlSeconds < 1 || trim($renewalId) === '') {
            throw new InvalidArgumentException('Renewal identity and positive TTL are required.');
        }

        return new self($this->id, $this->attemptId, $this->holder, $this->token, $this->acquisitionId, LeaseStatus::Active, $this->acquiredAt, $now->modify("+{$ttlSeconds} seconds"), $now, lastRenewalId: $renewalId);
    }

    public function release(ExecutionNodeRef $holder, LeaseToken $token, string $releaseId, DateTimeImmutable $now): self
    {
        $this->assertAuthority($holder, $token);
        if ($this->releaseId === $releaseId) {
            return $this;
        }
        if ($this->status !== LeaseStatus::Active) {
            throw ExecutionStateRejected::because(ExecutionStateRejectionReason::LeaseExpired, 'Only an active Lease can be released.');
        }
        if (trim($releaseId) === '') {
            throw new InvalidArgumentException('A release identity is required.');
        }

        return new self($this->id, $this->attemptId, $this->holder, $this->token, $this->acquisitionId, LeaseStatus::Released, $this->acquiredAt, $this->expiresAt, $this->renewedAt, $now, $this->lastRenewalId, $releaseId);
    }

    public function expire(DateTimeImmutable $now): self
    {
        if ($this->status === LeaseStatus::Expired) {
            return $this;
        }
        if ($this->status !== LeaseStatus::Active || $now < $this->expiresAt) {
            throw ExecutionStateRejected::because(ExecutionStateRejectionReason::InvalidTransition, 'Only an elapsed active Lease can expire.');
        }

        return new self($this->id, $this->attemptId, $this->holder, $this->token, $this->acquisitionId, LeaseStatus::Expired, $this->acquiredAt, $this->expiresAt, $this->renewedAt, lastRenewalId: $this->lastRenewalId);
    }

    public function isActiveAt(DateTimeImmutable $now): bool
    {
        return $this->status === LeaseStatus::Active && $now < $this->expiresAt;
    }

    private function assertAuthority(ExecutionNodeRef $holder, LeaseToken $token): void
    {
        if ($holder->value !== $this->holder->value) {
            throw ExecutionStateRejected::because(ExecutionStateRejectionReason::NotLeaseHolder, 'The execution node does not hold this Lease.');
        }
        if (! hash_equals($this->token->value, $token->value)) {
            throw ExecutionStateRejected::because(ExecutionStateRejectionReason::ForeignLease, 'The Lease token is foreign or stale.');
        }
    }
}
