<?php

declare(strict_types=1);

namespace Sifrious\Logres;

use DateTimeImmutable;
use InvalidArgumentException;

final readonly class RunnerLease
{
    public function __construct(
        public string $id,
        public RunId $runId,
        public ExecutionTargetId $targetId,
        public string $runnerId,
        public RunnerLeaseStatus $status,
        public DateTimeImmutable $leasedAt,
        public DateTimeImmutable $expiresAt,
        public ?DateTimeImmutable $acknowledgedAt = null,
        public ?DateTimeImmutable $completedAt = null,
    ) {
        if (trim($id) === '' || trim($runnerId) === '' || $expiresAt <= $leasedAt) {
            throw new InvalidArgumentException('A runner lease requires identities and a future expiry.');
        }
    }

    public static function offer(string $id, Run $run, string $runnerId, DateTimeImmutable $now, int $ttlSeconds): self
    {
        if ($run->dispatchAuthorization === null || $run->providerBindingStatus !== ProviderBindingStatus::AwaitingAcknowledgement) {
            throw new InvalidArgumentException('Only an authorized dispatched Run can be leased.');
        }
        if ($ttlSeconds < 1) {
            throw new InvalidArgumentException('A lease TTL must be positive.');
        }

        return new self($id, $run->id, $run->dispatchAuthorization->targetId, $runnerId, RunnerLeaseStatus::Offered, $now, $now->modify("+{$ttlSeconds} seconds"));
    }

    public function acknowledge(DateTimeImmutable $now): self
    {
        if ($this->status === RunnerLeaseStatus::Acknowledged) {
            return $this;
        }
        $this->assertActive($now);
        if ($this->status !== RunnerLeaseStatus::Offered) {
            throw new InvalidArgumentException('Only an offered lease can be acknowledged.');
        }

        return new self($this->id, $this->runId, $this->targetId, $this->runnerId, RunnerLeaseStatus::Acknowledged, $this->leasedAt, $this->expiresAt, $now);
    }

    public function complete(DateTimeImmutable $now): self
    {
        if ($this->status === RunnerLeaseStatus::Completed) {
            return $this;
        }
        $this->assertActive($now);
        if ($this->status !== RunnerLeaseStatus::Acknowledged) {
            throw new InvalidArgumentException('Only an acknowledged lease can complete.');
        }

        return new self($this->id, $this->runId, $this->targetId, $this->runnerId, RunnerLeaseStatus::Completed, $this->leasedAt, $this->expiresAt, $this->acknowledgedAt, $now);
    }

    public function reoffer(DateTimeImmutable $now, int $ttlSeconds): self
    {
        if ($this->status !== RunnerLeaseStatus::Offered || $now < $this->expiresAt || $ttlSeconds < 1) {
            throw new InvalidArgumentException('Only an expired unacknowledged lease can be reoffered.');
        }
        return new self($this->id, $this->runId, $this->targetId, $this->runnerId, RunnerLeaseStatus::Offered, $now, $now->modify("+{$ttlSeconds} seconds"));
    }

    public function heartbeat(DateTimeImmutable $now, int $ttlSeconds): self
    {
        $this->assertActive($now);
        if ($this->status !== RunnerLeaseStatus::Acknowledged || $ttlSeconds < 1) {
            throw new InvalidArgumentException('Only an acknowledged lease can be renewed.');
        }
        return new self($this->id, $this->runId, $this->targetId, $this->runnerId, $this->status, $this->leasedAt, $now->modify("+{$ttlSeconds} seconds"), $this->acknowledgedAt);
    }

    public function recover(DateTimeImmutable $now, int $ttlSeconds): self
    {
        if ($this->status !== RunnerLeaseStatus::Acknowledged || $now < $this->expiresAt || $ttlSeconds < 1) {
            throw new InvalidArgumentException('Only an expired acknowledged lease can be recovered.');
        }

        return new self(
            $this->id,
            $this->runId,
            $this->targetId,
            $this->runnerId,
            RunnerLeaseStatus::Acknowledged,
            $now,
            $now->modify("+{$ttlSeconds} seconds"),
            $this->acknowledgedAt,
        );
    }

    public function assertActive(DateTimeImmutable $now): void
    {
        if ($now >= $this->expiresAt) {
            throw new StaleRunnerLease($this->id);
        }
    }
}
