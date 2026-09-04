<?php

declare(strict_types=1);

namespace Sifrious\Logres\Tests;

use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Sifrious\Logres\AgentStepAction;
use Sifrious\Logres\AgentStepDetermination;
use Sifrious\Logres\AgentStepDeterminer;
use Sifrious\Logres\AgentStepEffect;
use Sifrious\Logres\AgentStepId;
use Sifrious\Logres\AgentStepLoop;
use Sifrious\Logres\AgentStepObservation;
use Sifrious\Logres\AgentStepRecord;
use Sifrious\Logres\AgentStepReentry;
use Sifrious\Logres\AgentStepStore;
use Sifrious\Logres\AttemptId;
use Sifrious\Logres\ExecutionNodeRef;
use Sifrious\Logres\ExecutionState;
use Sifrious\Logres\LeaseId;
use Sifrious\Logres\LeaseToken;
use Sifrious\Logres\LoopObservation;
use Sifrious\Logres\LoopOperation;
use Sifrious\Logres\LoopPolicy;
use Sifrious\Logres\LoopPolicyEvaluator;
use Sifrious\Logres\LoopPolicyOutcome;
use Sifrious\Logres\RunId;
use Sifrious\Logres\RunStatus;
use Sifrious\Logres\Tests\Fixtures\InMemoryExecutionStateStore;

final class AgentStepLoopTest extends TestCase
{
    #[Test]
    public function a_multi_step_run_survives_worker_restart_and_stops_deterministically(): void
    {
        [$stateStore, $stepStore, $effect, $reentry, $policy, $runId, $firstStep] = $this->fixture();

        $first = $this->loop($stateStore, $stepStore, $effect, $reentry)->run($runId, $firstStep, $policy, $this->now());
        self::assertSame(AgentStepAction::Invoke, $first->record?->determination->action);
        self::assertInstanceOf(AgentStepId::class, $first->nextStepId);

        $second = $this->loop($stateStore, $stepStore, $effect, $reentry)->run($runId, $first->nextStepId, $policy, $this->now()->modify('+1 second'));
        self::assertSame(AgentStepAction::Wait, $second->record?->determination->action);
        self::assertInstanceOf(AgentStepId::class, $second->nextStepId);

        $third = $this->loop($stateStore, $stepStore, $effect, $reentry)->run($runId, $second->nextStepId, $policy, $this->now()->modify('+6 seconds'));

        self::assertSame(AgentStepAction::Stop, $third->record?->determination->action);
        self::assertSame(LoopPolicyOutcome::PolicyExhausted, $third->record?->determination->policyDetermination->outcome);
        self::assertFalse($third->reentryScheduled);
        self::assertSame(RunStatus::Failed, $stateStore->find($runId)?->status);
        self::assertCount(3, $stepStore->history($runId));
        self::assertSame(2, $effect->logicalEffects);
        self::assertCount(2, $reentry->scheduled);
        self::assertSame(
            ['attempt:test:1', 'attempt:test:1', 'attempt:test:1'],
            array_map(static fn (AgentStepRecord $record): string => $record->determination->attemptId->value, $stepStore->history($runId)),
        );
    }

    #[Test]
    public function crash_after_effect_is_reconciled_without_duplicate_execution(): void
    {
        [$stateStore, $stepStore, $effect, $reentry, $policy, $runId, $firstStep] = $this->fixture();
        $effect->crashAfterEffectOnce = true;
        $loop = $this->loop($stateStore, $stepStore, $effect, $reentry);

        try {
            $loop->run($runId, $firstStep, $policy, $this->now());
            self::fail('The simulated worker crash must escape.');
        } catch (RuntimeException $error) {
            self::assertSame('worker crashed after effect', $error->getMessage());
        }

        self::assertFalse($stepStore->find($firstStep)?->isRecorded());
        $resumed = $this->loop($stateStore, $stepStore, $effect, $reentry)->run($runId, $firstStep, $policy, $this->now()->modify('+1 second'));

        self::assertTrue($resumed->record?->isRecorded());
        self::assertSame(1, $effect->logicalEffects);
        self::assertTrue($resumed->reentryScheduled);
    }

    #[Test]
    public function optimistic_concurrency_loss_reenters_without_acting_on_a_stale_decision(): void
    {
        [$stateStore, $stepStore, $effect, $reentry, $policy, $runId, $firstStep] = $this->fixture();
        $stepStore->loseNextReservation = true;

        $result = $this->loop($stateStore, $stepStore, $effect, $reentry)->run($runId, $firstStep, $policy, $this->now());

        self::assertTrue($result->concurrencyLost);
        self::assertSame($firstStep, $result->nextStepId);
        self::assertSame(0, $effect->logicalEffects);
        self::assertCount(1, $reentry->scheduled);
    }

    #[Test]
    public function redelivery_of_a_recorded_step_converges_effect_and_reentry_identities(): void
    {
        [$stateStore, $stepStore, $effect, $reentry, $policy, $runId, $firstStep] = $this->fixture();
        $loop = $this->loop($stateStore, $stepStore, $effect, $reentry);

        $first = $loop->run($runId, $firstStep, $policy, $this->now());
        $duplicate = $loop->run($runId, $firstStep, $policy, $this->now()->modify('+1 second'));

        self::assertSame($first->record?->determination->fingerprint(), $duplicate->record?->determination->fingerprint());
        self::assertSame(1, $effect->logicalEffects);
        self::assertCount(1, $reentry->scheduled);
    }

    private function loop(
        InMemoryExecutionStateStore $states,
        InMemoryAgentStepStore $steps,
        RecoverableAgentStepEffect $effects,
        RecordingAgentStepReentry $reentry,
    ): AgentStepLoop {
        return new AgentStepLoop($states, $steps, new ScriptedAgentStepDeterminer, $effects, $reentry);
    }

    /** @return array{InMemoryExecutionStateStore, InMemoryAgentStepStore, RecoverableAgentStepEffect, RecordingAgentStepReentry, LoopPolicy, RunId, AgentStepId} */
    private function fixture(): array
    {
        $runId = new RunId('run:test');
        $attemptId = new AttemptId('attempt:test:1');
        $token = new LeaseToken('secret-token');
        $state = ExecutionState::create($runId, $this->now())
            ->scheduleAttempt($attemptId, $this->now())
            ->acquireLease($attemptId, new LeaseId('lease:test'), new ExecutionNodeRef('node:test'), $token, 'acquire:test', $this->now(), 3600)
            ->start($attemptId, $token, $this->now());
        $states = new InMemoryExecutionStateStore;
        $states->create($state);
        $steps = new InMemoryAgentStepStore($states);

        return [
            $states,
            $steps,
            new RecoverableAgentStepEffect($states, $token),
            new RecordingAgentStepReentry,
            new LoopPolicy('test-loop', 'v1', $this->now()->modify('+1 hour'), 2, 2, 2, 2, 100, 100, 1, 1, 10),
            $runId,
            AgentStepId::forSequence($runId, $attemptId, 1),
        ];
    }

    private function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-09-04T13:00:00Z');
    }
}

final class ScriptedAgentStepDeterminer implements AgentStepDeterminer
{
    public function determine(AgentStepId $stepId, ExecutionState $state, LoopPolicy $policy, array $history, DateTimeImmutable $observedAt): AgentStepDetermination
    {
        $sequence = count($history) + 1;
        $action = match ($sequence) {
            1 => AgentStepAction::Invoke,
            2 => AgentStepAction::Wait,
            default => AgentStepAction::Stop,
        };
        $operation = $action === AgentStepAction::Invoke ? LoopOperation::AdvanceStep : LoopOperation::Observe;
        $policyDetermination = (new LoopPolicyEvaluator)->determine($policy, new LoopObservation(
            observedAt: $observedAt,
            operation: $operation,
            steps: count($history),
            attempts: count($state->attempts),
            toolCalls: 0,
            consecutiveFailures: 0,
            delegationDepth: 0,
            concurrentChildren: 0,
            needsInputSince: $action === AgentStepAction::Wait ? $observedAt : null,
        ));
        $attempt = $state->currentAttempt() ?? $state->attempts[array_key_last($state->attempts)];

        return new AgentStepDetermination(
            $stepId,
            $state->runId,
            $attempt->id,
            $sequence,
            $action,
            $state->version,
            $observedAt,
            "Deterministic test action {$action->value}.",
            $policyDetermination,
            reenterAt: $action === AgentStepAction::Wait ? $observedAt->modify('+5 seconds') : null,
        );
    }
}

final class InMemoryAgentStepStore implements AgentStepStore
{
    /** @var array<string, AgentStepRecord> */
    private array $records = [];
    public bool $loseNextReservation = false;

    public function __construct(private readonly InMemoryExecutionStateStore $states) {}

    public function find(AgentStepId $stepId): ?AgentStepRecord
    {
        return $this->records[$stepId->value] ?? null;
    }

    public function history(RunId $runId): array
    {
        $records = array_values(array_filter(
            $this->records,
            static fn (AgentStepRecord $record): bool => $record->determination->runId->value === $runId->value,
        ));
        usort($records, static fn (AgentStepRecord $left, AgentStepRecord $right): int => $left->determination->sequence <=> $right->determination->sequence);

        return $records;
    }

    public function reserve(AgentStepDetermination $determination): ?AgentStepRecord
    {
        if ($this->loseNextReservation) {
            $this->loseNextReservation = false;
            return null;
        }
        $existing = $this->find($determination->stepId);
        if ($existing !== null) {
            if (! hash_equals($existing->determination->fingerprint(), $determination->fingerprint())) {
                throw new InvalidArgumentException('Conflicting Step replay.');
            }
            return $existing;
        }
        if ($this->states->find($determination->runId)?->version !== $determination->expectedStateVersion) {
            return null;
        }
        foreach ($this->history($determination->runId) as $record) {
            if ($record->determination->sequence === $determination->sequence) {
                throw new InvalidArgumentException('Run Step sequence is already reserved.');
            }
        }

        return $this->records[$determination->stepId->value] = new AgentStepRecord($determination);
    }

    public function record(AgentStepObservation $observation): AgentStepRecord
    {
        $record = $this->find($observation->stepId) ?? throw new InvalidArgumentException('Step is not reserved.');
        $next = new AgentStepRecord($record->determination, $observation);
        if ($record->observation !== null && $record != $next) {
            throw new InvalidArgumentException('Conflicting Step observation.');
        }

        return $this->records[$observation->stepId->value] = $next;
    }
}

final class RecoverableAgentStepEffect implements AgentStepEffect
{
    /** @var array<string, AgentStepObservation> */
    private array $observations = [];
    public int $logicalEffects = 0;
    public bool $crashAfterEffectOnce = false;

    public function __construct(
        private readonly InMemoryExecutionStateStore $states,
        private readonly LeaseToken $token,
    ) {}

    public function reconcileOrPerform(AgentStepDetermination $determination): AgentStepObservation
    {
        $operation = $determination->operationIdentity();
        if (isset($this->observations[$operation])) {
            return $this->observations[$operation];
        }

        ++$this->logicalEffects;
        if ($determination->action === AgentStepAction::Stop) {
            $current = $this->states->find($determination->runId) ?? throw new RuntimeException('Missing state.');
            $next = $current->finish($determination->attemptId, $this->token, RunStatus::Failed, $determination->observedAt, 'Loop policy exhausted.');
            if (! $this->states->compareAndSwap($determination->runId, $current->version, $next)) {
                throw new RuntimeException('Terminal state transition lost concurrency.');
            }
        }
        $observation = $this->observations[$operation] = new AgentStepObservation(
            $determination->stepId,
            $operation,
            $determination->observedAt,
            detail: "Observed {$determination->action->value}.",
        );
        if ($this->crashAfterEffectOnce) {
            $this->crashAfterEffectOnce = false;
            throw new RuntimeException('worker crashed after effect');
        }

        return $observation;
    }
}

final class RecordingAgentStepReentry implements AgentStepReentry
{
    /** @var array<string, AgentStepId> */
    public array $scheduled = [];

    public function schedule(RunId $runId, AgentStepId $stepId, DateTimeImmutable $notBefore, string $idempotencyIdentity): void
    {
        $this->scheduled[$idempotencyIdentity] = $stepId;
    }
}
