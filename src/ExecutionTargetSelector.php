<?php

declare(strict_types=1);

namespace Sifrious\Logres;

final class ExecutionTargetSelector
{
    public function select(
        ExecutionTargetRequirements $requirements,
        array $candidates,
        ExecutionTargetAuthorization $authorization,
        string $selectedAt,
        ?ExecutionTargetId $manualTargetId = null,
    ): TargetSelectionResult {
        $contextual = array_values(array_filter(
            $candidates,
            static fn (ExecutionTargetCandidate $candidate): bool => $candidate->provider === $requirements->provider
                && $candidate->workspaceAuthority === $requirements->workspaceAuthority
                && $candidate->repositoryIdentity === $requirements->repositoryIdentity,
        ));
        $capable = array_values(array_filter(
            $contextual,
            static fn (ExecutionTargetCandidate $candidate): bool => in_array($requirements->agentAdapter, $candidate->agentAdapters, true)
                && array_diff($requirements->capabilities, $candidate->capabilities) === [],
        ));
        $operational = array_values(array_filter(
            $capable,
            static fn (ExecutionTargetCandidate $candidate): bool => $candidate->availability === TargetAvailability::Available
                && $candidate->health === TargetHealth::Healthy,
        ));
        $authorized = array_values(array_filter(
            $operational,
            fn (ExecutionTargetCandidate $candidate): bool => $authorization->allows($candidate, $requirements),
        ));

        if ($manualTargetId !== null) {
            return $this->manual($requirements, $contextual, $capable, $operational, $authorized, $manualTargetId, $selectedAt);
        }

        if ($contextual === []) {
            return $this->rejected('target_unavailable', 'The provider reported no target for the required workspace and repository.');
        }

        if ($capable === []) {
            return $this->rejected('target_incapable', 'No target satisfies the required agent and capabilities.');
        }

        if ($operational === []) {
            return $this->rejected('target_unavailable', 'No capable target is both available and healthy.');
        }

        if ($authorized === []) {
            return $this->rejected('target_unauthorized', 'The caller is not authorized for an operational target.');
        }

        if (count($authorized) > 1) {
            return $this->rejected('target_ambiguous', 'More than one eligible target remains and no ranking policy resolves them.');
        }

        return $this->selected($requirements, $authorized[0], $authorized, TargetSelectionReason::Automatic, $selectedAt);
    }

    private function manual(
        ExecutionTargetRequirements $requirements,
        array $contextual,
        array $capable,
        array $operational,
        array $authorized,
        ExecutionTargetId $manualTargetId,
        string $selectedAt,
    ): TargetSelectionResult {
        $matches = static fn (array $targets): array => array_values(array_filter(
            $targets,
            static fn (ExecutionTargetCandidate $candidate): bool => $candidate->id->value === $manualTargetId->value,
        ));

        if ($matches($contextual) === [] || $matches($capable) === []) {
            return $this->rejected('target_incapable', 'The requested target does not satisfy task requirements.');
        }

        if ($matches($operational) === []) {
            return $this->rejected('target_unavailable', 'The requested target is not available and healthy.');
        }

        $chosen = $matches($authorized);

        if ($chosen === []) {
            return $this->rejected('target_unauthorized', 'The caller is not authorized to override to the requested target.');
        }

        return $this->selected($requirements, $chosen[0], $authorized, TargetSelectionReason::ManualOverride, $selectedAt);
    }

    private function selected(
        ExecutionTargetRequirements $requirements,
        ExecutionTargetCandidate $target,
        array $eligible,
        TargetSelectionReason $reason,
        string $selectedAt,
    ): TargetSelectionResult {
        return new TargetSelectionResult(
            TargetSelectionStatus::Selected,
            new ExecutionTargetSelection(
                taskId: $requirements->taskId,
                target: $target,
                requirements: $requirements,
                reason: $reason,
                selectedAt: $selectedAt,
                alternateTargetIds: array_map(
                    static fn (ExecutionTargetCandidate $candidate): string => $candidate->id->value,
                    array_filter($eligible, static fn (ExecutionTargetCandidate $candidate): bool => $candidate->id->value !== $target->id->value),
                ),
            ),
        );
    }

    private function rejected(string $code, string $message): TargetSelectionResult
    {
        return new TargetSelectionResult(
            TargetSelectionStatus::Rejected,
            null,
            [new TargetSelectionFailure($code, $message)],
        );
    }
}
