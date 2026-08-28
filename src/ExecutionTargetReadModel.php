<?php

declare(strict_types=1);

namespace Sifrious\Logres;

final readonly class ExecutionTargetReadModel
{
    public function __construct(
        public string $taskId,
        public string $provider,
        public string $targetId,
        public string $availability,
        public string $health,
        public string $runtime,
        public string $environment,
        public string $workspaceAuthority,
        public string $repositoryIdentity,
        public array $agentAdapters,
        public array $requiredCapabilities,
        public array $availableCapabilities,
        public string $selectionReason,
        public string $selectedAt,
        public string $observedAt,
        public ?string $currentTaskId,
        public array $alternateTargetIds,
    ) {}

    public static function fromSelection(ExecutionTargetSelection $selection): self
    {
        return new self(
            taskId: $selection->taskId->value,
            provider: $selection->target->provider,
            targetId: $selection->target->id->value,
            availability: $selection->target->availability->value,
            health: $selection->target->health->value,
            runtime: $selection->target->runtime,
            environment: $selection->target->environment,
            workspaceAuthority: $selection->target->workspaceAuthority,
            repositoryIdentity: $selection->target->repositoryIdentity,
            agentAdapters: $selection->target->agentAdapters,
            requiredCapabilities: $selection->requirements->capabilities,
            availableCapabilities: $selection->target->capabilities,
            selectionReason: $selection->reason->value,
            selectedAt: $selection->selectedAt,
            observedAt: $selection->target->observedAt,
            currentTaskId: $selection->target->currentTaskId?->value,
            alternateTargetIds: $selection->alternateTargetIds,
        );
    }
}
