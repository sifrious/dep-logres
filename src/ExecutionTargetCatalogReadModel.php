<?php

declare(strict_types=1);

namespace Sifrious\Logres;

final readonly class ExecutionTargetCatalogReadModel
{
    public array $targets;

    public function __construct(array $targets)
    {
        $this->targets = array_values($targets);
    }

    public static function fromCandidates(array $candidates): self
    {
        return new self(array_map(static fn (ExecutionTargetCandidate $candidate): array => [
            'id' => $candidate->id->value,
            'provider' => $candidate->provider,
            'availability' => $candidate->availability->value,
            'health' => $candidate->health->value,
            'runtime' => $candidate->runtime,
            'workspace_authority' => $candidate->workspaceAuthority,
            'repository_identity' => $candidate->repositoryIdentity,
            'agent_adapters' => $candidate->agentAdapters,
            'capabilities' => $candidate->capabilities,
            'current_task_id' => $candidate->currentTaskId?->value,
            'observed_at' => $candidate->observedAt,
        ], $candidates));
    }
}
