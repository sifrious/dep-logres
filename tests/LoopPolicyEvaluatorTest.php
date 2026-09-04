<?php

declare(strict_types=1);

namespace Sifrious\Logres\Tests;

use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sifrious\Logres\EvidenceReference;
use Sifrious\Logres\LoopObservation;
use Sifrious\Logres\LoopOperation;
use Sifrious\Logres\LoopPolicy;
use Sifrious\Logres\LoopPolicyClause;
use Sifrious\Logres\LoopPolicyEvaluator;
use Sifrious\Logres\LoopPolicyOutcome;
use Sifrious\Logres\RequiredVerificationOutcome;

final class LoopPolicyEvaluatorTest extends TestCase
{
    #[Test]
    public function a_persisted_policy_is_complete_versioned_and_round_trips(): void
    {
        $policy = $this->policy();

        self::assertEquals($policy, LoopPolicy::fromArray($policy->toArray()));
        self::assertSame('loop.standard', $policy->toArray()['name']);
        self::assertSame('2026-09-04.1', $policy->toArray()['version']);

        $this->expectException(InvalidArgumentException::class);
        LoopPolicy::fromArray(['name' => 'incomplete']);
    }

    #[Test]
    public function below_each_applicable_limit_allows_the_requested_operation_and_records_remaining_budget(): void
    {
        $decision = $this->evaluate($this->observation(
            steps: 9,
            attempts: 2,
            toolCalls: 19,
            consecutiveFailures: 2,
            delegationDepth: 1,
            concurrentChildren: 1,
            tokensUsed: 999,
            costMicrosUsed: 1999,
            operation: LoopOperation::InvokeTool,
        ));

        self::assertSame(LoopPolicyOutcome::Continue, $decision->outcome);
        self::assertSame(LoopPolicyClause::WithinBudgets, $decision->clause);
        self::assertSame(1, $decision->remaining->steps);
        self::assertSame(1, $decision->remaining->attempts);
        self::assertSame(1, $decision->remaining->toolCalls);
        self::assertSame(1, $decision->remaining->tokens);
        self::assertSame(1, $decision->remaining->costMicros);
    }

    #[Test]
    #[DataProvider('exactLimitCases')]
    public function exact_limits_stop_before_an_over_budget_operation(LoopObservation $observation, LoopPolicyClause $clause): void
    {
        $decision = $this->evaluate($observation);

        self::assertSame(LoopPolicyOutcome::PolicyExhausted, $decision->outcome);
        self::assertSame($clause, $decision->clause);
        self::assertContains($clause, $decision->exhaustedClauses);
        self::assertSame('loop.standard', $decision->policyName);
        self::assertSame('2026-09-04.1', $decision->policyVersion);
    }

    public static function exactLimitCases(): array
    {
        $at = new DateTimeImmutable('2026-09-04T13:00:00Z');
        $base = static fn (
            LoopOperation $operation = LoopOperation::AdvanceStep,
            int $steps = 0,
            int $attempts = 0,
            int $toolCalls = 0,
            int $failures = 0,
            int $depth = 0,
            int $children = 0,
            ?int $tokens = null,
            ?int $cost = null,
        ): LoopObservation => new LoopObservation($at, $operation, $steps, $attempts, $toolCalls, $failures, $depth, $children, $tokens, $cost);

        return [
            'steps' => [$base(steps: 10), LoopPolicyClause::MaximumSteps],
            'attempts' => [$base(operation: LoopOperation::StartAttempt, attempts: 3), LoopPolicyClause::MaximumAttempts],
            'tool calls' => [$base(operation: LoopOperation::InvokeTool, toolCalls: 20), LoopPolicyClause::MaximumToolCalls],
            'repeated failures' => [$base(failures: 3), LoopPolicyClause::MaximumConsecutiveFailures],
            'tokens when observed' => [$base(tokens: 1000), LoopPolicyClause::MaximumTokens],
            'cost when observed' => [$base(cost: 2000), LoopPolicyClause::MaximumCost],
            'delegation depth' => [$base(operation: LoopOperation::Delegate, depth: 2), LoopPolicyClause::MaximumDelegationDepth],
            'concurrent children' => [$base(operation: LoopOperation::SpawnChild, children: 2), LoopPolicyClause::MaximumConcurrentChildren],
        ];
    }

    #[Test]
    public function provider_budgets_are_reported_unknown_and_not_invented_when_usage_is_unobservable(): void
    {
        $decision = $this->evaluate($this->observation(tokensUsed: null, costMicrosUsed: null));

        self::assertSame(LoopPolicyOutcome::Continue, $decision->outcome);
        self::assertNull($decision->remaining->tokens);
        self::assertNull($decision->remaining->costMicros);
    }

    #[Test]
    public function attempts_tools_delegation_and_children_only_exhaust_for_the_corresponding_operation(): void
    {
        $decision = $this->evaluate($this->observation(
            attempts: 3,
            toolCalls: 20,
            delegationDepth: 2,
            concurrentChildren: 2,
            operation: LoopOperation::Observe,
        ));

        self::assertSame(LoopPolicyOutcome::Continue, $decision->outcome);
    }

    #[Test]
    public function deadline_is_live_one_second_before_and_exhausted_at_the_exact_instant(): void
    {
        $before = $this->evaluate($this->observation(observedAt: new DateTimeImmutable('2026-09-04T13:59:59Z')));
        $at = $this->evaluate($this->observation(observedAt: new DateTimeImmutable('2026-09-04T14:00:00Z')));

        self::assertSame(LoopPolicyOutcome::Continue, $before->outcome);
        self::assertSame(1, $before->remaining->wallClockSeconds);
        self::assertSame(LoopPolicyOutcome::PolicyExhausted, $at->outcome);
        self::assertSame(LoopPolicyClause::WallClockDeadline, $at->clause);
    }

    #[Test]
    public function cancellation_and_authorization_revocation_are_explicit_terminal_policy_observations(): void
    {
        $cancelled = $this->evaluate($this->observation(cancellationRequested: true));
        $revoked = $this->evaluate($this->observation(authorizationActive: false));

        self::assertSame(LoopPolicyOutcome::Cancelled, $cancelled->outcome);
        self::assertSame(LoopPolicyClause::CancellationRequested, $cancelled->clause);
        self::assertSame(LoopPolicyOutcome::AuthorizationRevoked, $revoked->outcome);
        self::assertSame(LoopPolicyClause::AuthorizationRevoked, $revoked->clause);
    }

    #[Test]
    public function successful_completion_requires_independent_verification_when_configured(): void
    {
        $claimOnly = $this->evaluate($this->observation(completionClaimed: true));
        $verified = $this->evaluate($this->observation(
            completionClaimed: true,
            verification: RequiredVerificationOutcome::Passed,
        ));

        self::assertSame(LoopPolicyOutcome::Continue, $claimOnly->outcome);
        self::assertSame(LoopPolicyOutcome::SuccessfulCompletion, $verified->outcome);
    }

    #[Test]
    public function unavailable_required_verification_stops_as_unresolved_needs_input(): void
    {
        $decision = $this->evaluate($this->observation(
            completionClaimed: true,
            verification: RequiredVerificationOutcome::Unavailable,
        ));

        self::assertSame(LoopPolicyOutcome::UnresolvedNeedsInput, $decision->outcome);
        self::assertSame(LoopPolicyClause::VerificationUnavailable, $decision->clause);
    }

    #[Test]
    public function human_wait_is_bounded_and_stops_at_the_exact_limit(): void
    {
        $since = new DateTimeImmutable('2026-09-04T12:59:00Z');
        $waiting = $this->evaluate($this->observation(
            observedAt: new DateTimeImmutable('2026-09-04T13:03:59Z'),
            needsInputSince: $since,
        ));
        $stopped = $this->evaluate($this->observation(
            observedAt: new DateTimeImmutable('2026-09-04T13:04:00Z'),
            needsInputSince: $since,
        ));

        self::assertSame(LoopPolicyOutcome::AwaitingInput, $waiting->outcome);
        self::assertSame(1, $waiting->remaining->inputWaitSeconds);
        self::assertSame(LoopPolicyOutcome::UnresolvedNeedsInput, $stopped->outcome);
        self::assertSame(LoopPolicyClause::NeedsInputUnresolved, $stopped->clause);
    }

    #[Test]
    public function terminal_determinations_are_monotonic_and_preserve_the_original_evidence(): void
    {
        $evidence = new EvidenceReference('cancellation', 'request:42', '2026-09-04T13:00:00Z', 1);
        $terminal = $this->evaluate($this->observation(cancellationRequested: true, evidence: [$evidence]));
        $reentry = (new LoopPolicyEvaluator)->determine(
            $this->policy(),
            $this->observation(completionClaimed: true, verification: RequiredVerificationOutcome::Passed),
            $terminal,
        );

        self::assertSame($terminal, $reentry);
        self::assertSame([$evidence], $reentry->evidence);
    }

    #[Test]
    public function an_attempt_cannot_switch_to_a_different_policy_version(): void
    {
        $terminal = $this->evaluate($this->observation(cancellationRequested: true));
        $other = new LoopPolicy(
            name: 'loop.standard',
            version: 'different',
            wallClockDeadline: new DateTimeImmutable('2026-09-04T14:00:00Z'),
            maximumSteps: 10,
            maximumAttempts: 3,
            maximumToolCalls: 20,
            maximumConsecutiveFailures: 3,
            maximumTokens: 1000,
            maximumCostMicros: 2000,
            maximumDelegationDepth: 2,
            maximumConcurrentChildren: 2,
            maximumInputWaitSeconds: 300,
        );

        $this->expectException(InvalidArgumentException::class);
        (new LoopPolicyEvaluator)->determine($other, $this->observation(), $terminal);
    }

    private function policy(): LoopPolicy
    {
        return new LoopPolicy(
            name: 'loop.standard',
            version: '2026-09-04.1',
            wallClockDeadline: new DateTimeImmutable('2026-09-04T14:00:00Z'),
            maximumSteps: 10,
            maximumAttempts: 3,
            maximumToolCalls: 20,
            maximumConsecutiveFailures: 3,
            maximumTokens: 1000,
            maximumCostMicros: 2000,
            maximumDelegationDepth: 2,
            maximumConcurrentChildren: 2,
            maximumInputWaitSeconds: 300,
        );
    }

    /** @param list<EvidenceReference> $evidence */
    private function observation(
        ?DateTimeImmutable $observedAt = null,
        LoopOperation $operation = LoopOperation::AdvanceStep,
        int $steps = 0,
        int $attempts = 0,
        int $toolCalls = 0,
        int $consecutiveFailures = 0,
        int $delegationDepth = 0,
        int $concurrentChildren = 0,
        ?int $tokensUsed = null,
        ?int $costMicrosUsed = null,
        ?DateTimeImmutable $needsInputSince = null,
        bool $cancellationRequested = false,
        bool $authorizationActive = true,
        bool $completionClaimed = false,
        RequiredVerificationOutcome $verification = RequiredVerificationOutcome::NotRun,
        array $evidence = [],
    ): LoopObservation {
        return new LoopObservation(
            observedAt: $observedAt ?? new DateTimeImmutable('2026-09-04T13:00:00Z'),
            operation: $operation,
            steps: $steps,
            attempts: $attempts,
            toolCalls: $toolCalls,
            consecutiveFailures: $consecutiveFailures,
            delegationDepth: $delegationDepth,
            concurrentChildren: $concurrentChildren,
            tokensUsed: $tokensUsed,
            costMicrosUsed: $costMicrosUsed,
            needsInputSince: $needsInputSince,
            cancellationRequested: $cancellationRequested,
            authorizationActive: $authorizationActive,
            completionClaimed: $completionClaimed,
            verification: $verification,
            evidence: $evidence,
        );
    }

    private function evaluate(LoopObservation $observation): \Sifrious\Logres\LoopPolicyDetermination
    {
        return (new LoopPolicyEvaluator)->determine($this->policy(), $observation);
    }
}
