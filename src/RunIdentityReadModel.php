<?php

declare(strict_types=1);

namespace Sifrious\Logres;

final readonly class RunIdentityReadModel
{
    public function __construct(
        public string $id,
        public string $requestId,
        public string $taskId,
        public string $promptId,
        public int $promptVersion,
        public string $promptCompilerVersion,
        public string $promptProvenanceHash,
        public string $targetId,
        public string $provider,
        public string $runtime,
        public string $repositoryIdentity,
        public string $workspaceAuthority,
        public string $targetSelectedAt,
        public string $targetObservedAt,
        public array $policyVersions,
        public string $initiatingActor,
        public string $createdAt,
        public string $providerBindingStatus,
        public ?string $providerExecutionId,
        public ?string $dispatchedAt,
        public ?string $acknowledgedAt,
        public ?string $identityIssue,
    ) {}

    public static function fromRun(Run $run): self
    {
        $provenance = $run->provenance;
        $selection = $provenance->targetSelection;
        $target = $selection->target;

        return new self(
            id: $run->id->value,
            requestId: $provenance->requestId->value,
            taskId: $provenance->taskId->value,
            promptId: $provenance->promptId->value,
            promptVersion: $provenance->promptVersion,
            promptCompilerVersion: $provenance->promptCompilerVersion,
            promptProvenanceHash: $provenance->promptProvenanceHash,
            targetId: $target->id->value,
            provider: $target->provider,
            runtime: $target->runtime,
            repositoryIdentity: $target->repositoryIdentity,
            workspaceAuthority: $target->workspaceAuthority,
            targetSelectedAt: $selection->selectedAt,
            targetObservedAt: $target->observedAt,
            policyVersions: $provenance->policyVersions,
            initiatingActor: $provenance->initiatingActor,
            createdAt: $provenance->createdAt,
            providerBindingStatus: $run->providerBindingStatus->value,
            providerExecutionId: $run->providerExecutionId?->canonical(),
            dispatchedAt: $run->dispatchedAt,
            acknowledgedAt: $run->acknowledgedAt,
            identityIssue: $run->identityIssue,
        );
    }
}
