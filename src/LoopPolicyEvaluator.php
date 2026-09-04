<?php

declare(strict_types=1);

namespace Sifrious\Logres;

use InvalidArgumentException;

final readonly class LoopPolicyEvaluator
{
    public function determine(
        LoopPolicy $policy,
        LoopObservation $observation,
        ?LoopPolicyDetermination $prior = null,
    ): LoopPolicyDetermination {
        if ($prior !== null && ($prior->policyName !== $policy->name || $prior->policyVersion !== $policy->version)) {
            throw new InvalidArgumentException('A loop cannot switch policy provenance during an attempt.');
        }
        if ($prior?->isTerminal()) {
            return $prior;
        }

        $remaining = $this->remaining($policy, $observation);

        if ($observation->cancellationRequested) {
            return $this->result($policy, $observation, $remaining, LoopPolicyOutcome::Cancelled, LoopPolicyClause::CancellationRequested, 'Authorized cancellation was observed.');
        }
        if (! $observation->authorizationActive) {
            return $this->result($policy, $observation, $remaining, LoopPolicyOutcome::AuthorizationRevoked, LoopPolicyClause::AuthorizationRevoked, 'Execution authorization is no longer active.');
        }
        if ($observation->completionClaimed) {
            if (! $policy->independentVerificationRequired || $observation->verification === RequiredVerificationOutcome::Passed) {
                return $this->result($policy, $observation, $remaining, LoopPolicyOutcome::SuccessfulCompletion, LoopPolicyClause::SuccessfulCompletion, 'Completion satisfies the policy verification requirement.');
            }
            if ($observation->verification === RequiredVerificationOutcome::Unavailable) {
                return $this->result($policy, $observation, $remaining, LoopPolicyOutcome::UnresolvedNeedsInput, LoopPolicyClause::VerificationUnavailable, 'Required independent verification is unavailable.');
            }
        }

        if ($observation->observedAt >= $policy->wallClockDeadline) {
            return $this->exhausted($policy, $observation, $remaining, [LoopPolicyClause::WallClockDeadline]);
        }

        if ($observation->needsInputSince !== null) {
            $waiting = $observation->observedAt->getTimestamp() - $observation->needsInputSince->getTimestamp();
            if ($waiting >= $policy->maximumInputWaitSeconds) {
                return $this->result($policy, $observation, $remaining, LoopPolicyOutcome::UnresolvedNeedsInput, LoopPolicyClause::NeedsInputUnresolved, 'Required human input was not supplied within policy.');
            }

            return $this->result($policy, $observation, $remaining, LoopPolicyOutcome::AwaitingInput, LoopPolicyClause::AwaitInput, 'Waiting for required human input within the bounded input window.');
        }

        $exhausted = [];
        if ($observation->steps >= $policy->maximumSteps) {
            $exhausted[] = LoopPolicyClause::MaximumSteps;
        }
        if ($observation->consecutiveFailures >= $policy->maximumConsecutiveFailures) {
            $exhausted[] = LoopPolicyClause::MaximumConsecutiveFailures;
        }
        if ($observation->tokensUsed !== null && $observation->tokensUsed >= $policy->maximumTokens) {
            $exhausted[] = LoopPolicyClause::MaximumTokens;
        }
        if ($observation->costMicrosUsed !== null && $observation->costMicrosUsed >= $policy->maximumCostMicros) {
            $exhausted[] = LoopPolicyClause::MaximumCost;
        }
        if ($observation->operation === LoopOperation::StartAttempt && $observation->attempts >= $policy->maximumAttempts) {
            $exhausted[] = LoopPolicyClause::MaximumAttempts;
        }
        if ($observation->operation === LoopOperation::InvokeTool && $observation->toolCalls >= $policy->maximumToolCalls) {
            $exhausted[] = LoopPolicyClause::MaximumToolCalls;
        }
        if ($observation->operation === LoopOperation::Delegate && $observation->delegationDepth >= $policy->maximumDelegationDepth) {
            $exhausted[] = LoopPolicyClause::MaximumDelegationDepth;
        }
        if ($observation->operation === LoopOperation::SpawnChild && $observation->concurrentChildren >= $policy->maximumConcurrentChildren) {
            $exhausted[] = LoopPolicyClause::MaximumConcurrentChildren;
        }
        if ($exhausted !== []) {
            return $this->exhausted($policy, $observation, $remaining, $exhausted);
        }

        return $this->result($policy, $observation, $remaining, LoopPolicyOutcome::Continue, LoopPolicyClause::WithinBudgets, 'The requested operation remains within every applicable budget.');
    }

    private function remaining(LoopPolicy $policy, LoopObservation $observation): LoopBudgetRemaining
    {
        $inputRemaining = null;
        if ($observation->needsInputSince !== null) {
            $elapsed = $observation->observedAt->getTimestamp() - $observation->needsInputSince->getTimestamp();
            $inputRemaining = max(0, $policy->maximumInputWaitSeconds - $elapsed);
        }

        return new LoopBudgetRemaining(
            steps: max(0, $policy->maximumSteps - $observation->steps),
            attempts: max(0, $policy->maximumAttempts - $observation->attempts),
            wallClockSeconds: max(0, $policy->wallClockDeadline->getTimestamp() - $observation->observedAt->getTimestamp()),
            toolCalls: max(0, $policy->maximumToolCalls - $observation->toolCalls),
            consecutiveFailures: max(0, $policy->maximumConsecutiveFailures - $observation->consecutiveFailures),
            tokens: $observation->tokensUsed === null ? null : max(0, $policy->maximumTokens - $observation->tokensUsed),
            costMicros: $observation->costMicrosUsed === null ? null : max(0, $policy->maximumCostMicros - $observation->costMicrosUsed),
            delegationDepth: max(0, $policy->maximumDelegationDepth - $observation->delegationDepth),
            concurrentChildren: max(0, $policy->maximumConcurrentChildren - $observation->concurrentChildren),
            inputWaitSeconds: $inputRemaining,
        );
    }

    /** @param list<LoopPolicyClause> $clauses */
    private function exhausted(LoopPolicy $policy, LoopObservation $observation, LoopBudgetRemaining $remaining, array $clauses): LoopPolicyDetermination
    {
        return new LoopPolicyDetermination(
            policyName: $policy->name,
            policyVersion: $policy->version,
            observedAt: $observation->observedAt->format(DATE_ATOM),
            outcome: LoopPolicyOutcome::PolicyExhausted,
            clause: $clauses[0],
            reason: 'One or more finite loop budgets are exhausted.',
            remaining: $remaining,
            exhaustedClauses: $clauses,
            evidence: $observation->evidence,
        );
    }

    private function result(
        LoopPolicy $policy,
        LoopObservation $observation,
        LoopBudgetRemaining $remaining,
        LoopPolicyOutcome $outcome,
        LoopPolicyClause $clause,
        string $reason,
    ): LoopPolicyDetermination {
        return new LoopPolicyDetermination(
            policyName: $policy->name,
            policyVersion: $policy->version,
            observedAt: $observation->observedAt->format(DATE_ATOM),
            outcome: $outcome,
            clause: $clause,
            reason: $reason,
            remaining: $remaining,
            evidence: $observation->evidence,
        );
    }
}
