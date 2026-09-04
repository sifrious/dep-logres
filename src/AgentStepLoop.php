<?php

declare(strict_types=1);

namespace Sifrious\Logres;

use DateTimeImmutable;
use InvalidArgumentException;
use RuntimeException;

/**
 * Runs one recoverable determination/effect/observation cycle per invocation.
 *
 * Queue transport, transactions, clocks, and delayed delivery remain host
 * concerns. Canonical execution lifecycle remains owned by ExecutionState.
 */
final readonly class AgentStepLoop
{
    public function __construct(
        private ExecutionStateStore $states,
        private AgentStepStore $steps,
        private AgentStepDeterminer $determiner,
        private AgentStepEffect $effects,
        private AgentStepReentry $reentry,
    ) {}

    public function run(
        RunId $runId,
        AgentStepId $stepId,
        LoopPolicy $policy,
        DateTimeImmutable $observedAt,
    ): AgentStepCycleResult {
        $existing = $this->steps->find($stepId);
        if ($existing !== null) {
            return $this->resume($existing, $observedAt);
        }

        $state = $this->requireState($runId);
        $history = $this->steps->history($runId);
        $determination = $this->determiner->determine($stepId, $state, $policy, $history, $observedAt);
        $this->assertDetermination($determination, $runId, $stepId, $state, $policy, $history);

        $reserved = $this->steps->reserve($determination);
        if ($reserved === null) {
            $this->schedule($runId, $stepId, $observedAt, 'stale');

            return new AgentStepCycleResult(null, true, $stepId, true);
        }

        return $this->resume($reserved, $observedAt);
    }

    private function resume(AgentStepRecord $record, DateTimeImmutable $observedAt): AgentStepCycleResult
    {
        if (! $record->isRecorded()) {
            $observation = $record->determination->action->requiresEffect()
                ? $this->effects->reconcileOrPerform($record->determination)
                : AgentStepObservation::noEffect($record->determination, $observedAt);

            $record = $this->steps->record($observation);
        }

        $state = $this->requireState($record->determination->runId);
        if ($state->status->isTerminal()) {
            return new AgentStepCycleResult($record, false);
        }

        $history = $this->steps->history($state->runId);
        $latest = $history === [] ? null : $history[array_key_last($history)];
        if ($latest !== null && $latest->determination->sequence > $record->determination->sequence) {
            return new AgentStepCycleResult($record, false);
        }

        $attempt = $state->currentAttempt() ?? $this->lastAttempt($state);
        $sequence = $record->determination->sequence + 1;
        $next = AgentStepId::forSequence($state->runId, $attempt->id, $sequence);
        $notBefore = $record->determination->reenterAt ?? $observedAt;
        $this->schedule($state->runId, $next, $notBefore, $record->determination->stepId->value);

        return new AgentStepCycleResult($record, true, $next);
    }

    /** @param list<AgentStepRecord> $history */
    private function assertDetermination(
        AgentStepDetermination $determination,
        RunId $runId,
        AgentStepId $stepId,
        ExecutionState $state,
        LoopPolicy $policy,
        array $history,
    ): void {
        $expectedSequence = $history === [] ? 1 : $history[array_key_last($history)]->determination->sequence + 1;
        $attempt = $state->currentAttempt() ?? $this->lastAttempt($state);

        if ($determination->runId->value !== $runId->value
            || $determination->stepId->value !== $stepId->value
            || $determination->attemptId->value !== $attempt->id->value
            || $determination->sequence !== $expectedSequence
            || $determination->expectedStateVersion !== $state->version
            || $determination->policyDetermination->policyName !== $policy->name
            || $determination->policyDetermination->policyVersion !== $policy->version) {
            throw new InvalidArgumentException('Agent Step determination does not match durable state, history, or policy.');
        }
    }

    private function schedule(RunId $runId, AgentStepId $stepId, DateTimeImmutable $notBefore, string $cause): void
    {
        $this->reentry->schedule(
            $runId,
            $stepId,
            $notBefore,
            'agent-step-reentry:sha256:'.hash('sha256', implode("\0", [$runId->value, $stepId->value, $cause])),
        );
    }

    private function requireState(RunId $runId): ExecutionState
    {
        return $this->states->find($runId)
            ?? throw new RuntimeException("Execution state {$runId->value} was not found.");
    }

    private function lastAttempt(ExecutionState $state): ExecutionAttempt
    {
        if ($state->attempts === []) {
            throw new RuntimeException('An Agent Step requires an explicit canonical execution Attempt.');
        }

        return $state->attempts[array_key_last($state->attempts)];
    }
}
