<?php

declare(strict_types=1);

namespace Sifrious\Logres;

use DateTimeImmutable;

final class ExecutionTargetSelector
{
    public const POLICY_VERSION = 'execution-target-selection-v1';

    public function select(ExecutionTargetRequirements $requirements, array $candidates, ExecutionTargetAuthorization $authorization, string $selectedAt, ?ExecutionTargetId $manualTargetId = null, ?string $overrideActor = null): TargetSelectionResult
    {
        $evaluations = $this->evaluate($requirements, $candidates, $authorization, new DateTimeImmutable($selectedAt));
        $eligible = array_values(array_filter($evaluations, static fn (CandidateEvaluation $evaluation): bool => $evaluation->eligible()));

        if ($manualTargetId !== null) {
            foreach ($evaluations as $evaluation) {
                if ($evaluation->candidate->id->value !== $manualTargetId->value) continue;
                if (! $evaluation->eligible()) return $this->rejected($evaluation->rejectionReasons[0]->value, 'The requested target failed the automatic eligibility policy.', $evaluations);
                return $this->selected($requirements, $evaluation->candidate, $evaluations, $selectedAt, TargetSelectionReason::ManualOverride, $manualTargetId, $overrideActor ?? $authorization->callerIdentity);
            }
            return $this->rejected(CandidateRejectionReason::TargetNotInInventory->value, 'The requested target was not present in provider inventory.', $evaluations);
        }

        if ($eligible === []) return $this->rejected($evaluations === [] ? 'target_unavailable' : $this->summaryCode($evaluations), 'No inventory candidate satisfied every execution requirement.', $evaluations);

        return $this->selected($requirements, $eligible[0]->candidate, $evaluations, $selectedAt, TargetSelectionReason::Automatic);
    }

    private function evaluate(ExecutionTargetRequirements $requirements, array $candidates, ExecutionTargetAuthorization $authorization, DateTimeImmutable $selectedAt): array
    {
        $identityCounts = [];
        foreach ($candidates as $candidate) {
            if (! $candidate instanceof ExecutionTargetCandidate) {
                throw new \InvalidArgumentException('Target inventory must contain only provider-returned candidates.');
            }
            $identityCounts[$candidate->id->value] = ($identityCounts[$candidate->id->value] ?? 0) + 1;
        }
        $evaluations = [];
        foreach ($candidates as $candidate) {
            $reasons = [];
            if ($identityCounts[$candidate->id->value] > 1) $reasons[] = CandidateRejectionReason::DuplicateInventoryIdentity;
            if ($candidate->provider !== $requirements->provider) $reasons[] = CandidateRejectionReason::TargetNotInInventory;
            if ($requirements->providerAccountId !== null && $candidate->providerAccountId !== $requirements->providerAccountId) $reasons[] = CandidateRejectionReason::WrongProviderAccount;
            if ($requirements->providerProjectId !== null && $candidate->providerProjectId !== $requirements->providerProjectId) $reasons[] = CandidateRejectionReason::WrongProviderProject;
            if ($candidate->workspaceAuthority !== $requirements->workspaceAuthority) $reasons[] = CandidateRejectionReason::WorkspaceMismatch;
            if ($candidate->repositoryIdentity !== $requirements->repositoryIdentity) $reasons[] = CandidateRejectionReason::RepositoryMismatch;
            if ($requirements->requiredExecutionClass !== null && $candidate->executionClass !== $requirements->requiredExecutionClass) $reasons[] = CandidateRejectionReason::ExecutionClassDisallowed;
            if (! in_array($requirements->agentAdapter, $candidate->agentAdapters, true)) $reasons[] = CandidateRejectionReason::UnsupportedRuntime;
            if (array_diff($requirements->capabilities, $candidate->capabilities) !== []) $reasons[] = CandidateRejectionReason::MissingCapability;
            if ($candidate->availability === TargetAvailability::Offline || $candidate->health === TargetHealth::Unhealthy) $reasons[] = CandidateRejectionReason::Offline;
            if ($candidate->availability === TargetAvailability::Busy) $reasons[] = CandidateRejectionReason::ConcurrencyExhausted;
            if (($candidate->availability === TargetAvailability::Degraded || $candidate->health === TargetHealth::Degraded) && ! $requirements->allowDegraded) $reasons[] = CandidateRejectionReason::DegradedNotAllowed;
            if ($candidate->availability !== TargetAvailability::Available && ! ($requirements->allowDegraded && $candidate->availability === TargetAvailability::Degraded)) $reasons[] = CandidateRejectionReason::Unavailable;
            if ($candidate->health === TargetHealth::Unknown) $reasons[] = CandidateRejectionReason::Unavailable;
            if ($candidate->concurrencyLimit !== null && $candidate->currentWorkCount >= $candidate->concurrencyLimit) $reasons[] = CandidateRejectionReason::ConcurrencyExhausted;
            $age = $selectedAt->getTimestamp() - (new DateTimeImmutable($candidate->observedAt))->getTimestamp();
            if ($age < 0 || $age > $requirements->maximumObservationAgeSeconds) $reasons[] = CandidateRejectionReason::StaleObservation;
            if (! in_array($candidate->id->value, $authorization->targetIds, true)) $reasons[] = CandidateRejectionReason::UnauthorizedTarget;
            if (! in_array($requirements->workspaceAuthority, $authorization->workspaceAuthorities, true)) $reasons[] = CandidateRejectionReason::UnauthorizedWorkspace;
            if (! in_array($requirements->repositoryIdentity, $authorization->repositoryIdentities, true)) $reasons[] = CandidateRejectionReason::RepositoryMismatch;
            $evaluations[] = new CandidateEvaluation($candidate, $reasons);
        }
        usort($evaluations, static fn (CandidateEvaluation $left, CandidateEvaluation $right): int => $left->candidate->id->value <=> $right->candidate->id->value);
        return $evaluations;
    }

    private function selected(ExecutionTargetRequirements $requirements, ExecutionTargetCandidate $target, array $evaluations, string $selectedAt, TargetSelectionReason $reason, ?ExecutionTargetId $requested = null, ?string $overrideActor = null): TargetSelectionResult
    {
        $selection = new ExecutionTargetSelection($requirements->taskId, $target, $requirements, $reason, $selectedAt, array_values(array_map(static fn (CandidateEvaluation $evaluation): string => $evaluation->candidate->id->value, array_filter($evaluations, static fn (CandidateEvaluation $evaluation): bool => $evaluation->candidate->id->value !== $target->id->value))), $evaluations, self::POLICY_VERSION, $requested, $overrideActor);
        return new TargetSelectionResult(TargetSelectionStatus::Selected, $selection);
    }

    private function rejected(string $code, string $message, array $evaluations): TargetSelectionResult
    {
        return new TargetSelectionResult(TargetSelectionStatus::Rejected, null, [new TargetSelectionFailure($code, $message)], $evaluations);
    }

    private function summaryCode(array $evaluations): string
    {
        $reasons = array_merge(...array_map(static fn (CandidateEvaluation $evaluation): array => $evaluation->rejectionReasons, $evaluations));
        if (array_filter($reasons, static fn (CandidateRejectionReason $reason): bool => in_array($reason, [CandidateRejectionReason::MissingCapability, CandidateRejectionReason::UnsupportedRuntime], true))) return 'target_incapable';
        if (array_filter($reasons, static fn (CandidateRejectionReason $reason): bool => in_array($reason, [CandidateRejectionReason::UnauthorizedTarget, CandidateRejectionReason::UnauthorizedWorkspace], true))) return 'target_unauthorized';
        return 'target_unavailable';
    }
}
