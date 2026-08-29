<?php

declare(strict_types=1);

namespace Sifrious\Logres;

use InvalidArgumentException;

final readonly class ExecutionTargetSelection
{
    public array $alternateTargetIds;
    public ExecutionTargetCandidate $automaticTarget;

    public function __construct(
        public TaskId $taskId,
        public ExecutionTargetCandidate $target,
        public ExecutionTargetRequirements $requirements,
        public TargetSelectionReason $reason,
        public string $selectedAt,
        array $alternateTargetIds,
        public string $selectionPolicyVersion = 'execution-target-v1',
        public array $candidateEvaluations = [],
        ?ExecutionTargetCandidate $automaticTarget = null,
        public ?array $override = null,
        public string $selectionReason = 'Only eligible target.',
        public ?string $tieBreakReason = null,
    ) {
        if ($taskId->value !== $requirements->taskId->value || preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d+)?Z$/', $selectedAt) !== 1) {
            throw new InvalidArgumentException('A target selection requires matching task identity and an explicit UTC selection timestamp.');
        }

        if ($target->provider !== $requirements->provider
            || $target->workspaceAuthority !== $requirements->workspaceAuthority
            || $target->repositoryIdentity !== $requirements->repositoryIdentity
            || array_diff($requirements->capabilities, $target->capabilitySnapshot !== null ? $target->capabilitySnapshot->capabilities : $target->capabilities) !== []
            || $target->availability !== TargetAvailability::Available
            || $target->health !== TargetHealth::Healthy) {
            throw new InvalidArgumentException('A selected target must satisfy every requirement and be operational.');
        }

        $normalizedAlternates = array_values(array_unique($alternateTargetIds));
        sort($normalizedAlternates);
        $this->alternateTargetIds = array_values(array_filter(
            $normalizedAlternates,
            fn (string $id): bool => $id !== $target->id->value,
        ));
        $this->automaticTarget = $automaticTarget ?? $target;
    }
}
