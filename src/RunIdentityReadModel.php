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
        public string $environment,
        public string $repositoryIdentity,
        public string $workspaceAuthority,
        public string $targetSelectedAt,
        public string $targetObservedAt,
        public array $policyVersions,
        public array $requestedPermissions,
        public string $initiatingActor,
        public string $createdAt,
        public string $providerBindingStatus,
        public ?string $providerExecutionId,
        public ?string $dispatchedAt,
        public ?string $acknowledgedAt,
        public ?string $identityIssue,
        public ?array $dispatchAuthorization,
        public ?array $preDispatchValidationFailure,
        public array $executionIdentity,
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
            environment: $target->environment,
            repositoryIdentity: $target->repositoryIdentity,
            workspaceAuthority: $target->workspaceAuthority,
            targetSelectedAt: $selection->selectedAt,
            targetObservedAt: $target->observedAt,
            policyVersions: $provenance->policyVersions,
            requestedPermissions: $provenance->requestedPermissions,
            initiatingActor: $provenance->initiatingActor,
            createdAt: $provenance->createdAt,
            providerBindingStatus: $run->providerBindingStatus->value,
            providerExecutionId: $run->providerExecutionId?->canonical(),
            dispatchedAt: $run->dispatchedAt,
            acknowledgedAt: $run->acknowledgedAt,
            identityIssue: $run->identityIssue,
            dispatchAuthorization: $run->dispatchAuthorization === null ? null : [
                'grant_id' => $run->dispatchAuthorization->grantId,
                'actor' => $run->dispatchAuthorization->actor,
                'workspace_path' => $run->dispatchAuthorization->workspacePath->value,
                'environment' => $run->dispatchAuthorization->environment,
                'runtime' => $run->dispatchAuthorization->runtime,
                'permissions' => $run->dispatchAuthorization->permissions,
                'policy_version' => $run->dispatchAuthorization->policyVersion,
                'authorized_at' => $run->dispatchAuthorization->authorizedAt,
            ],
            preDispatchValidationFailure: $run->preDispatchValidationFailure === null ? null : [
                'code' => $run->preDispatchValidationFailure->code,
                'message' => $run->preDispatchValidationFailure->message,
                'failed_at' => $run->preDispatchValidationFailure->failedAt,
            ],
            executionIdentity: $provenance->executionIdentity?->toArray()
                ?? ExecutionProvenanceClassification::missingRecord(),
        );
    }
}
