<?php

declare(strict_types=1);

namespace Sifrious\Logres;

use InvalidArgumentException;

final readonly class ExecutionTargetSelection
{
    public array $alternateTargetIds;
    public array $candidateEvaluations;

    public function __construct(
        public TaskId $taskId,
        public ExecutionTargetCandidate $target,
        public ExecutionTargetRequirements $requirements,
        public TargetSelectionReason $reason,
        public string $selectedAt,
        array $alternateTargetIds,
        array $candidateEvaluations = [],
        public string $policyVersion = ExecutionTargetSelector::POLICY_VERSION,
        public ?ExecutionTargetId $requestedTargetId = null,
        public ?string $overrideActor = null,
    ) {
        if ($taskId->value !== $requirements->taskId->value || preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d+)?Z$/', $selectedAt) !== 1) {
            throw new InvalidArgumentException('A target selection requires matching task identity and an explicit UTC selection timestamp.');
        }

        if ($target->provider !== $requirements->provider
            || $target->workspaceAuthority !== $requirements->workspaceAuthority
            || $target->repositoryIdentity !== $requirements->repositoryIdentity
            || ! in_array($requirements->agentAdapter, $target->agentAdapters, true)
            || array_diff($requirements->capabilities, $target->capabilities) !== []
            || (! $requirements->allowDegraded && ($target->availability !== TargetAvailability::Available || $target->health !== TargetHealth::Healthy))
            || ($requirements->allowDegraded && ! in_array($target->availability, [TargetAvailability::Available, TargetAvailability::Degraded], true))
            || ($requirements->allowDegraded && ! in_array($target->health, [TargetHealth::Healthy, TargetHealth::Degraded], true))) {
            throw new InvalidArgumentException('A selected target must satisfy every requirement and be operational.');
        }

        $normalizedAlternates = array_values(array_unique($alternateTargetIds));
        sort($normalizedAlternates);
        $this->alternateTargetIds = array_values(array_filter(
            $normalizedAlternates,
            fn (string $id): bool => $id !== $target->id->value,
        ));
        foreach ($candidateEvaluations as $evaluation) {
            if (! $evaluation instanceof CandidateEvaluation) throw new InvalidArgumentException('A target selection must preserve candidate evaluations.');
        }
        $this->candidateEvaluations = array_values($candidateEvaluations);
        if (trim($policyVersion) === '' || ($reason === TargetSelectionReason::ManualOverride && ($requestedTargetId === null || trim((string) $overrideActor) === ''))) {
            throw new InvalidArgumentException('Selection policy and complete override provenance are required.');
        }
    }
}
