<?php

declare(strict_types=1);

namespace Sifrious\Logres;

use DateTimeImmutable;

final class ExecutionTargetSelector
{
    public const POLICY_VERSION = 'execution-target-v2';

    public function select(ExecutionTargetRequirements $requirements, array $candidates, ExecutionTargetAuthorization $authorization, string $selectedAt, ?ExecutionTargetId $manualTargetId = null, ?string $overrideReason = null): TargetSelectionResult
    {
        usort($candidates, static fn (ExecutionTargetCandidate $a, ExecutionTargetCandidate $b): int => strcmp($a->id->value, $b->id->value));
        $evaluations = array_map(fn (ExecutionTargetCandidate $candidate): ExecutionTargetEvaluation => $this->evaluate($requirements, $candidate, $authorization, $selectedAt), $candidates);
        $eligible = array_values(array_filter($evaluations, static fn (ExecutionTargetEvaluation $evaluation): bool => $evaluation->eligible));
        usort($eligible, fn (ExecutionTargetEvaluation $a, ExecutionTargetEvaluation $b): int => strcmp($this->rankKey($requirements, $a->candidate), $this->rankKey($requirements, $b->candidate)));

        $ranked = [];
        foreach ($eligible as $rank => $evaluation) {
            $ranked[$evaluation->candidate->id->value] = new ExecutionTargetEvaluation($evaluation->candidate, true, $evaluation->matchedCapabilities, [], $evaluation->policyChecks, $rank + 1, $this->rankKey($requirements, $evaluation->candidate));
        }
        foreach ($evaluations as $index => $evaluation) {
            if (isset($ranked[$evaluation->candidate->id->value])) $evaluations[$index] = $ranked[$evaluation->candidate->id->value];
        }
        $eligible = array_values($ranked);

        if ($eligible === []) {
            $code = $candidates === [] ? 'NO_TARGETS_DISCOVERED' : 'NO_ELIGIBLE_TARGET';
            return new TargetSelectionResult(TargetSelectionStatus::NeedsTarget, null, [new TargetSelectionFailure($code, 'No candidate satisfies every target-selection policy check.', $evaluations)]);
        }

        $automatic = $eligible[0]->candidate;
        $effective = $automatic;
        $reason = TargetSelectionReason::Automatic;
        $override = null;
        if ($manualTargetId !== null) {
            $requested = array_values(array_filter($evaluations, static fn (ExecutionTargetEvaluation $e): bool => $e->candidate->id->value === $manualTargetId->value));
            if ($requested === [] || ! $requested[0]->eligible) {
                return new TargetSelectionResult(TargetSelectionStatus::Rejected, null, [new TargetSelectionFailure('TARGET_OVERRIDE_REJECTED', 'The requested override target did not pass the same policy checks as automatic selection.', $requested)]);
            }
            $effective = $requested[0]->candidate;
            $reason = TargetSelectionReason::ManualOverride;
            $override = ['requested_target_id' => $manualTargetId->value, 'actor' => $authorization->callerIdentity, 'reason' => $overrideReason ?? 'Manual target override.', 'authorization_result' => 'authorized', 'timestamp' => $selectedAt];
        }

        $tieBreak = count($eligible) > 1 ? 'Eligible candidates sorted by preference, execution class, then stable target identity.' : null;
        return new TargetSelectionResult(TargetSelectionStatus::Selected, new ExecutionTargetSelection(
            taskId: $requirements->taskId, target: $effective, requirements: $requirements, reason: $reason, selectedAt: $selectedAt,
            alternateTargetIds: array_values(array_map(static fn (ExecutionTargetEvaluation $e): string => $e->candidate->id->value, array_filter($eligible, static fn (ExecutionTargetEvaluation $e): bool => $e->candidate->id->value !== $effective->id->value))),
            selectionPolicyVersion: self::POLICY_VERSION, candidateEvaluations: $evaluations, automaticTarget: $automatic, override: $override,
            selectionReason: $manualTargetId === null ? ($tieBreak ?? 'Only eligible target.') : 'Validated manual override changed the effective target.', tieBreakReason: $tieBreak,
        ));
    }

    private function evaluate(ExecutionTargetRequirements $requirements, ExecutionTargetCandidate $candidate, ExecutionTargetAuthorization $authorization, string $selectedAt): ExecutionTargetEvaluation
    {
        $observedCapabilities = $candidate->capabilitySnapshot !== null ? $candidate->capabilitySnapshot->capabilities : $candidate->capabilities;
        $capabilitiesObservedAt = $candidate->capabilitySnapshot !== null ? $candidate->capabilitySnapshot->observedAt : new DateTimeImmutable($candidate->observedAt);
        $missing = array_values(array_diff($requirements->capabilities, $observedCapabilities));
        $age = (new DateTimeImmutable($selectedAt))->getTimestamp() - $capabilitiesObservedAt->getTimestamp();
        $checks = ['provider' => $candidate->provider === $requirements->provider, 'workspace' => $candidate->workspaceAuthority === $requirements->workspaceAuthority, 'repository' => $candidate->repositoryIdentity === $requirements->repositoryIdentity, 'execution_class' => in_array($candidate->executionClass, $requirements->allowedExecutionClasses, true), 'capabilities' => $missing === [], 'availability' => $candidate->availability === TargetAvailability::Available, 'health' => $candidate->health === TargetHealth::Healthy, 'freshness' => $age >= 0 && $age <= $requirements->maximumSnapshotAgeSeconds, 'resources' => $candidate->availableSlots > 0, 'authorization' => $authorization->allows($candidate, $requirements)];
        $codes = ['provider' => 'TARGET_PROVIDER_MISMATCH', 'workspace' => 'TARGET_WORKSPACE_MISMATCH', 'repository' => 'TARGET_WORKSPACE_MISMATCH', 'execution_class' => 'TARGET_EXECUTION_CLASS_FORBIDDEN', 'capabilities' => 'TARGET_CAPABILITY_MISMATCH', 'availability' => 'TARGET_UNAVAILABLE', 'health' => 'TARGET_UNAVAILABLE', 'freshness' => 'TARGET_STALE', 'resources' => 'TARGET_RESOURCE_EXHAUSTED', 'authorization' => 'TARGET_UNAUTHORIZED'];
        $rejections = [];
        foreach ($checks as $check => $passed) if (! $passed) $rejections[] = ['code' => $codes[$check], 'check' => $check, 'details' => $check === 'capabilities' ? $missing : []];
        return new ExecutionTargetEvaluation($candidate, $rejections === [], array_values(array_intersect($requirements->capabilities, $observedCapabilities)), $rejections, $checks, 0, $this->rankKey($requirements, $candidate));
    }

    private function rankKey(ExecutionTargetRequirements $requirements, ExecutionTargetCandidate $candidate): string
    {
        $preferred = $requirements->preferredTargetId === $candidate->id->value ? '0' : '1';
        $classes = ['local' => '0', 'customer-owned' => '1', 'managed-cloud' => '2', 'provider-hosted' => '3'];
        return $preferred.':'.($classes[$candidate->executionClass] ?? '9').':'.$candidate->id->value;
    }
}
