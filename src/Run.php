<?php

declare(strict_types=1);

namespace Sifrious\Logres;

use InvalidArgumentException;

final readonly class Run
{
    public function __construct(
        public RunId $id,
        public RunProvenance $provenance,
        public ProviderBindingStatus $providerBindingStatus,
        public ?ProviderExecutionId $providerExecutionId = null,
        public ?string $dispatchedAt = null,
        public ?string $acknowledgedAt = null,
        public ?string $identityIssue = null,
    ) {
        $this->assertState();
    }

    public static function create(RunId $id, RunProvenance $provenance): self
    {
        return new self($id, $provenance, ProviderBindingStatus::NotDispatched);
    }

    public function awaitingAcknowledgement(string $dispatchedAt): self
    {
        if ($this->providerBindingStatus !== ProviderBindingStatus::NotDispatched) {
            throw new InvalidArgumentException('Only a not-dispatched Run can await acknowledgement.');
        }

        return new self($this->id, $this->provenance, ProviderBindingStatus::AwaitingAcknowledgement, dispatchedAt: $dispatchedAt);
    }

    public function acknowledgementUncertain(string $reason): self
    {
        if ($this->providerBindingStatus !== ProviderBindingStatus::AwaitingAcknowledgement) {
            throw new InvalidArgumentException('Only an awaiting Run can become acknowledgement-uncertain.');
        }

        return new self($this->id, $this->provenance, ProviderBindingStatus::AcknowledgementUncertain, dispatchedAt: $this->dispatchedAt, identityIssue: $reason);
    }

    public function acknowledged(ProviderExecutionId $providerExecutionId, string $acknowledgedAt): self
    {
        return new self($this->id, $this->provenance, ProviderBindingStatus::Acknowledged, $providerExecutionId, $this->dispatchedAt, $acknowledgedAt);
    }

    public function conflictingAcknowledgement(string $reason): self
    {
        return new self($this->id, $this->provenance, ProviderBindingStatus::ConflictingAcknowledgement, $this->providerExecutionId, $this->dispatchedAt, $this->acknowledgedAt, $reason);
    }

    public function reconciliationRequired(string $reason): self
    {
        return new self($this->id, $this->provenance, ProviderBindingStatus::ReconciliationRequired, $this->providerExecutionId, $this->dispatchedAt, $this->acknowledgedAt, $reason);
    }

    private function assertState(): void
    {
        $timestamp = static fn (?string $value): bool => $value !== null && preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d+)?Z$/', $value) === 1;
        $valid = match ($this->providerBindingStatus) {
            ProviderBindingStatus::NotDispatched => $this->providerExecutionId === null && $this->dispatchedAt === null && $this->acknowledgedAt === null && $this->identityIssue === null,
            ProviderBindingStatus::AwaitingAcknowledgement => $this->providerExecutionId === null && $timestamp($this->dispatchedAt) && $this->acknowledgedAt === null && $this->identityIssue === null,
            ProviderBindingStatus::Acknowledged => $this->providerExecutionId !== null && $timestamp($this->dispatchedAt) && $timestamp($this->acknowledgedAt) && $this->identityIssue === null,
            ProviderBindingStatus::AcknowledgementUncertain, ProviderBindingStatus::ReconciliationRequired => $this->providerExecutionId === null && $timestamp($this->dispatchedAt) && $this->acknowledgedAt === null && trim((string) $this->identityIssue) !== '',
            ProviderBindingStatus::ConflictingAcknowledgement => ($this->dispatchedAt === null || $timestamp($this->dispatchedAt)) && trim((string) $this->identityIssue) !== '',
        };

        if (! $valid) {
            throw new InvalidArgumentException('Run provider-binding state is inconsistent.');
        }
    }
}
