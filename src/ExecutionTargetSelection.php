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
        if (trim($selectionPolicyVersion) === '' || $candidateEvaluations === []) {
            throw new InvalidArgumentException('A target selection requires a policy version and complete candidate evaluations.');
        }
        $eligibleIds = [];
        foreach ($candidateEvaluations as $evaluation) {
            if (! $evaluation instanceof ExecutionTargetEvaluation) {
                throw new InvalidArgumentException('Candidate evaluations must use the canonical target evaluation contract.');
            }
            if ($evaluation->eligible) {
                $eligibleIds[] = $evaluation->candidate->id->value;
            }
        }
        if (! in_array($target->id->value, $eligibleIds, true) || ! in_array($this->automaticTarget->id->value, $eligibleIds, true)) {
            throw new InvalidArgumentException('Effective and automatic targets must both be eligible frozen candidates.');
        }
        if (($reason === TargetSelectionReason::ManualOverride) !== ($override !== null)) {
            throw new InvalidArgumentException('Manual selection and override provenance must agree.');
        }
        if ($override !== null && (! isset($override['requested_target_id'], $override['actor'], $override['authorization_result'], $override['timestamp']) || trim((string) $override['actor']) === '')) {
            throw new InvalidArgumentException('Override provenance requires requested target, actor, policy decision, and timestamp.');
        }
    }
}
