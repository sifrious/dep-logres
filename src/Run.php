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
        public ?DispatchAuthorizationSnapshot $dispatchAuthorization = null,
        public ?PreDispatchValidationFailure $preDispatchValidationFailure = null,
    ) {
        $this->assertState();
    }

    public static function create(RunId $id, RunProvenance $provenance): self
    {
        if ($provenance->executionIdentity === null || ! $provenance->executionIdentity->isDispatchable()) {
            throw new InvalidArgumentException('Every new Run requires complete canonical Stacks workspace provenance.');
        }

        return new self($id, $provenance, ProviderBindingStatus::NotDispatched);
    }

    public static function restoreLegacy(RunId $id, RunProvenance $provenance): self
    {
        return new self($id, $provenance, ProviderBindingStatus::NotDispatched);
    }

    public function awaitingAcknowledgement(string $dispatchedAt): self
    {
        if ($this->providerBindingStatus !== ProviderBindingStatus::NotDispatched) {
            throw new InvalidArgumentException('Only a not-dispatched Run can await acknowledgement.');
        }

        if ($this->dispatchAuthorization === null) {
            throw new InvalidArgumentException('A Run requires an immutable dispatch authorization before dispatch.');
        }

        return new self($this->id, $this->provenance, ProviderBindingStatus::AwaitingAcknowledgement, dispatchedAt: $dispatchedAt, dispatchAuthorization: $this->dispatchAuthorization);
    }

    public function revalidateForDispatch(StacksExecutionContext $currentObservation): self
    {
        if ($this->dispatchAuthorization === null || $this->provenance->executionIdentity === null) {
            throw new InvalidArgumentException('Dispatch revalidation requires approved immutable Stacks provenance.');
        }
        $this->provenance->executionIdentity->assertSameDispatchEvidence($currentObservation);
        if (! hash_equals($this->dispatchAuthorization->executionIdentityFingerprint, $currentObservation->dispatchEvidenceFingerprint())) {
            throw new InvalidArgumentException('Revision evidence changed between approval and dispatch.');
        }

        return $this;
    }

    public function validationBlocked(string $code, string $message, string $failedAt): self
    {
        if ($this->providerBindingStatus !== ProviderBindingStatus::NotDispatched || $this->dispatchAuthorization !== null) {
            throw new InvalidArgumentException('Only an unauthorized not-dispatched Run can be blocked by provider validation.');
        }

        return new self(
            $this->id,
            $this->provenance,
            ProviderBindingStatus::ValidationBlocked,
            preDispatchValidationFailure: new PreDispatchValidationFailure($code, $message, $failedAt),
        );
    }

    public function authorized(DispatchAuthorizationDecision $decision): self
    {
        if (! $decision->allowed || $decision->snapshot === null || $this->providerBindingStatus !== ProviderBindingStatus::NotDispatched || $this->dispatchAuthorization !== null) {
            throw new InvalidArgumentException('Only an allowed decision can authorize a not-dispatched Run.');
        }

        $snapshot = $decision->snapshot;
        $target = $this->provenance->targetSelection->target;

        if ($snapshot->actor !== $this->provenance->initiatingActor
            || $snapshot->targetId->value !== $target->id->value
            || $snapshot->repositoryIdentity->value !== $target->repositoryIdentity
            || $snapshot->workspaceAuthority->value !== $target->workspaceAuthority
            || $this->provenance->executionIdentity === null
            || ! hash_equals($snapshot->executionIdentityFingerprint, $this->provenance->executionIdentity->dispatchEvidenceFingerprint())
            || $snapshot->environment !== $target->environment
            || $snapshot->runtime !== $target->runtime
            || array_diff($this->provenance->requestedPermissions, $snapshot->permissions) !== []) {
            throw new InvalidArgumentException('Dispatch authorization must match the immutable Run provenance.');
        }

        return new self($this->id, $this->provenance, ProviderBindingStatus::NotDispatched, dispatchAuthorization: $snapshot);
    }

    public function acknowledgementUncertain(string $reason): self
    {
        if ($this->providerBindingStatus !== ProviderBindingStatus::AwaitingAcknowledgement) {
            throw new InvalidArgumentException('Only an awaiting Run can become acknowledgement-uncertain.');
        }

        return new self($this->id, $this->provenance, ProviderBindingStatus::AcknowledgementUncertain, dispatchedAt: $this->dispatchedAt, identityIssue: $reason, dispatchAuthorization: $this->dispatchAuthorization);
    }

    public function dispatchFailed(string $reason): self
    {
        if ($this->providerBindingStatus !== ProviderBindingStatus::AwaitingAcknowledgement || trim($reason) === '') {
            throw new InvalidArgumentException('Only an awaiting Run can record an explicit dispatch failure.');
        }

        return new self($this->id, $this->provenance, ProviderBindingStatus::DispatchFailed, dispatchedAt: $this->dispatchedAt, identityIssue: $reason, dispatchAuthorization: $this->dispatchAuthorization);
    }

    public function acknowledged(ProviderExecutionId $providerExecutionId, string $acknowledgedAt): self
    {
        if (! in_array($this->providerBindingStatus, [ProviderBindingStatus::AwaitingAcknowledgement, ProviderBindingStatus::AcknowledgementUncertain], true)) {
            throw new InvalidArgumentException('Only a dispatched Run can be acknowledged.');
        }

        return new self($this->id, $this->provenance, ProviderBindingStatus::Acknowledged, $providerExecutionId, $this->dispatchedAt, $acknowledgedAt, dispatchAuthorization: $this->dispatchAuthorization);
    }

    public function conflictingAcknowledgement(string $reason): self
    {
        return new self($this->id, $this->provenance, ProviderBindingStatus::ConflictingAcknowledgement, $this->providerExecutionId, $this->dispatchedAt, $this->acknowledgedAt, $reason, $this->dispatchAuthorization);
    }

    public function reconciliationRequired(string $reason): self
    {
        return new self($this->id, $this->provenance, ProviderBindingStatus::ReconciliationRequired, $this->providerExecutionId, $this->dispatchedAt, $this->acknowledgedAt, $reason, $this->dispatchAuthorization);
    }

    private function assertState(): void
    {
        if ($this->dispatchAuthorization !== null && ! $this->authorizationMatchesProvenance($this->dispatchAuthorization)) {
            throw new InvalidArgumentException('Dispatch authorization must match the immutable Run provenance.');
        }

        $timestamp = static fn (?string $value): bool => $value !== null && preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d+)?Z$/', $value) === 1;
        $valid = match ($this->providerBindingStatus) {
            ProviderBindingStatus::NotDispatched => $this->providerExecutionId === null && $this->dispatchedAt === null && $this->acknowledgedAt === null && $this->identityIssue === null,
            ProviderBindingStatus::ValidationBlocked => $this->dispatchAuthorization === null && $this->providerExecutionId === null && $this->dispatchedAt === null && $this->acknowledgedAt === null && $this->identityIssue === null && $this->preDispatchValidationFailure !== null,
            ProviderBindingStatus::AwaitingAcknowledgement => $this->dispatchAuthorization !== null && $this->providerExecutionId === null && $timestamp($this->dispatchedAt) && $this->acknowledgedAt === null && $this->identityIssue === null,
            ProviderBindingStatus::Acknowledged => $this->dispatchAuthorization !== null && $this->providerExecutionId !== null && $timestamp($this->dispatchedAt) && $timestamp($this->acknowledgedAt) && $this->identityIssue === null,
            ProviderBindingStatus::AcknowledgementUncertain, ProviderBindingStatus::ReconciliationRequired => $this->dispatchAuthorization !== null && $this->providerExecutionId === null && $timestamp($this->dispatchedAt) && $this->acknowledgedAt === null && trim((string) $this->identityIssue) !== '',
            ProviderBindingStatus::DispatchFailed => $this->dispatchAuthorization !== null && $this->providerExecutionId === null && $timestamp($this->dispatchedAt) && $this->acknowledgedAt === null && trim((string) $this->identityIssue) !== '',
            ProviderBindingStatus::ConflictingAcknowledgement => ($this->dispatchedAt === null || ($this->dispatchAuthorization !== null && $timestamp($this->dispatchedAt))) && trim((string) $this->identityIssue) !== '',
        };

        if (! $valid) {
            throw new InvalidArgumentException('Run provider-binding state is inconsistent.');
        }

        if ($this->providerBindingStatus !== ProviderBindingStatus::ValidationBlocked && $this->preDispatchValidationFailure !== null) {
            throw new InvalidArgumentException('Only a validation-blocked Run can contain pre-dispatch validation failure evidence.');
        }
    }

    private function authorizationMatchesProvenance(DispatchAuthorizationSnapshot $snapshot): bool
    {
        $target = $this->provenance->targetSelection->target;

        return $snapshot->actor === $this->provenance->initiatingActor
            && $snapshot->targetId->value === $target->id->value
            && $snapshot->repositoryIdentity->value === $target->repositoryIdentity
            && $snapshot->workspaceAuthority->value === $target->workspaceAuthority
            && $this->provenance->executionIdentity !== null
            && hash_equals($snapshot->executionIdentityFingerprint, $this->provenance->executionIdentity->dispatchEvidenceFingerprint())
            && $snapshot->environment === $target->environment
            && $snapshot->runtime === $target->runtime
            && array_diff($this->provenance->requestedPermissions, $snapshot->permissions) === [];
    }
}
