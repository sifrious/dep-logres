<?php

declare(strict_types=1);

namespace Sifrious\Logres;

use InvalidArgumentException;

/**
 * Provider-neutral projection across package-owned objects. It owns no new lifecycle identity.
 */
final readonly class LoopComposition
{
    /** @var list<string> */
    public array $decisionReferences;

    /** @var list<LoopCheckpoint> */
    public array $checkpoints;

    /** @var list<LoopHandoffReference> */
    public array $phaseHandoffs;

    /** @var list<LoopTaskComposition> */
    public array $tasks;

    public LoopDetermination $determination;

    /**
     * @param list<string> $decisionReferences
     * @param list<LoopCheckpoint> $checkpoints
     * @param list<LoopHandoffReference> $phaseHandoffs
     * @param list<LoopTaskComposition> $tasks
     */
    public function __construct(
        public ExecutionRequest $request,
        array $decisionReferences,
        array $checkpoints,
        public ?TaskPlan $plan,
        array $phaseHandoffs,
        array $tasks,
        public ?string $zeroWorkDecisionReference = null,
        public ?LoopInterventionReference $intervention = null,
    ) {
        if ($request->origin === null) {
            throw new InvalidArgumentException('A Loop composition must preserve its original input reference.');
        }

        $this->decisionReferences = $this->decisionReferences($decisionReferences);
        $this->checkpoints = $this->checkpoints($checkpoints);
        $this->phaseHandoffs = $this->phaseHandoffs($phaseHandoffs);
        $this->tasks = $this->taskCompositions($tasks);

        $this->assertShape();
        $this->determination = $this->determine();
    }

    private function assertShape(): void
    {
        foreach ($this->checkpoints as $checkpoint) {
            if (! in_array($checkpoint->decisionReference, $this->decisionReferences, true)) {
                throw new InvalidArgumentException('Every checkpoint decision must be retained by the Loop composition.');
            }
        }

        if ($this->zeroWorkDecisionReference !== null
            && ! in_array($this->zeroWorkDecisionReference, $this->decisionReferences, true)) {
            throw new InvalidArgumentException('A zero-work decision must be retained by the Loop composition.');
        }

        if ($this->plan === null) {
            if ($this->phaseHandoffs !== [] || $this->tasks !== []) {
                throw new InvalidArgumentException('Tasks and handoffs cannot be composed without a plan.');
            }

            if ($this->zeroWorkDecisionReference === null) {
                throw new InvalidArgumentException('A Loop without a plan requires a durable zero-work decision.');
            }

            return;
        }

        if ($this->zeroWorkDecisionReference !== null) {
            throw new InvalidArgumentException('A materialized plan cannot also be a zero-work outcome.');
        }

        if ($this->plan->requestId->value !== $this->request->id->value) {
            throw new InvalidArgumentException('A composed plan must preserve the execution request identity.');
        }

        if ($this->plan->tasks === []) {
            throw new InvalidArgumentException('Zero-work deliberation must not create an empty plan.');
        }

        $checkpointTypes = array_map(
            static fn (LoopCheckpoint $checkpoint): LoopCheckpointType => $checkpoint->type,
            $this->checkpoints,
        );
        if ($checkpointTypes !== [LoopCheckpointType::ArchitecturePlacement, LoopCheckpointType::SimplicityCut]) {
            throw new InvalidArgumentException('Architecture placement and simplicity cut must complete, in order, before work materializes.');
        }

        foreach ($this->phaseHandoffs as $handoff) {
            if ($handoff->originReference !== $this->plan->id->value) {
                throw new InvalidArgumentException('A phase handoff must retain its originating plan reference.');
            }
        }

        $planned = [];
        foreach ($this->plan->tasks as $task) {
            $planned[$task->id->value] = $task;
        }

        $composed = [];
        $runIds = [];
        $handoffIds = [];
        $externalIds = [];
        $externalIdempotency = [];
        foreach ($this->tasks as $task) {
            $id = $task->task->id->value;
            if (! isset($planned[$id]) || $planned[$id] != $task->task) {
                throw new InvalidArgumentException('A Loop task composition must contain the exact task from its plan.');
            }
            $composed[$id] = true;

            if ($task->run !== null && isset($runIds[$task->run->id->value])) {
                throw new InvalidArgumentException('One Run cannot execute more than one composed task.');
            }
            if ($task->run !== null) {
                $runIds[$task->run->id->value] = true;
            }

            if ($task->handoff !== null) {
                $handoffIdentity = $task->handoff->idempotencyIdentity();
                if (isset($handoffIds[$handoffIdentity])) {
                    throw new InvalidArgumentException('A ticket handoff cannot be duplicated.');
                }
                $handoffIds[$handoffIdentity] = true;
            }

            if ($task->externalWork !== null) {
                $external = $task->externalWork;
                $providerIdentity = $external->provider."\0".$external->externalIdentifier;
                if (isset($externalIds[$providerIdentity]) || isset($externalIdempotency[$external->idempotencyIdentity])) {
                    throw new InvalidArgumentException('External ticket and idempotency identities must be unique within a Loop.');
                }
                $externalIds[$providerIdentity] = true;
                $externalIdempotency[$external->idempotencyIdentity] = true;
            }
        }

        if (count($planned) !== count($composed) || array_diff_key($planned, $composed) !== []) {
            throw new InvalidArgumentException('Every planned task must appear exactly once in the Loop composition.');
        }
    }

    private function determine(): LoopDetermination
    {
        if ($this->intervention !== null) {
            return new LoopDetermination(
                $this->intervention->disposition,
                'An owning package supplied an explicit intervention.',
                $this->intervention->taskId,
                $this->intervention->reference,
            );
        }

        if ($this->plan === null) {
            return new LoopDetermination(
                LoopDisposition::Complete,
                'Deliberation concluded that no materialized work is required.',
                decisionReference: $this->zeroWorkDecisionReference,
            );
        }

        $tasks = $this->tasks;
        usort($tasks, static fn (LoopTaskComposition $left, LoopTaskComposition $right): int => $left->task->id->value <=> $right->task->id->value);

        foreach ($tasks as $task) {
            if ($task->task->status === TaskStatus::WaitingForInput) {
                return new LoopDetermination(LoopDisposition::Clarify, 'The owning task is waiting for explicit input.', $task->task->id);
            }
            if ($task->task->status === TaskStatus::Canceled) {
                return new LoopDetermination(LoopDisposition::Stop, 'The owning task was canceled.', $task->task->id);
            }
            if ($task->verification?->requiredVerification === RequiredVerificationOutcome::Unavailable) {
                return new LoopDetermination(LoopDisposition::Escalate, 'Required verification is unavailable.', $task->task->id);
            }
            if ($task->task->status === TaskStatus::Failed
                || $task->verification?->requiredVerification === RequiredVerificationOutcome::Failed) {
                return new LoopDetermination(LoopDisposition::Rework, 'Execution or required verification failed for the owning task.', $task->task->id);
            }
        }

        foreach ($tasks as $task) {
            if ($task->task->status === TaskStatus::Skipped) {
                continue;
            }
            if ($task->task->status !== TaskStatus::Succeeded || ! $task->hasVerifiedEvidence()) {
                return new LoopDetermination(
                    LoopDisposition::Advance,
                    'At least one task has not satisfied its result, verification, and evidence contracts.',
                    $task->task->id,
                );
            }
        }

        return new LoopDetermination(LoopDisposition::Complete, 'Every materialized task satisfied its completion and evidence contracts.');
    }

    /** @param list<string> $references @return list<string> */
    private function decisionReferences(array $references): array
    {
        foreach ($references as $reference) {
            if (! is_string($reference) || trim($reference) === '') {
                throw new InvalidArgumentException('Loop decision references must be non-empty strings.');
            }
        }

        if (count(array_unique($references)) !== count($references)) {
            throw new InvalidArgumentException('Loop decision references cannot be duplicated.');
        }

        return array_values($references);
    }

    /** @param list<mixed> $checkpoints @return list<LoopCheckpoint> */
    private function checkpoints(array $checkpoints): array
    {
        foreach ($checkpoints as $checkpoint) {
            if (! $checkpoint instanceof LoopCheckpoint) {
                throw new InvalidArgumentException('Loop checkpoints must contain LoopCheckpoint values.');
            }
        }
        usort($checkpoints, static fn (LoopCheckpoint $left, LoopCheckpoint $right): int => $left->sequence <=> $right->sequence);

        $types = array_map(static fn (LoopCheckpoint $checkpoint): string => $checkpoint->type->value, $checkpoints);
        $sequences = array_map(static fn (LoopCheckpoint $checkpoint): int => $checkpoint->sequence, $checkpoints);
        if (count(array_unique($types)) !== count($types) || count(array_unique($sequences)) !== count($sequences)) {
            throw new InvalidArgumentException('Loop checkpoint types and sequences cannot be duplicated.');
        }

        return array_values($checkpoints);
    }

    /** @param list<mixed> $handoffs @return list<LoopHandoffReference> */
    private function phaseHandoffs(array $handoffs): array
    {
        $identities = [];
        foreach ($handoffs as $handoff) {
            if (! $handoff instanceof LoopHandoffReference || $handoff->type !== LoopHandoffType::Phase) {
                throw new InvalidArgumentException('Phase handoffs must contain phase LoopHandoffReference values.');
            }
            if (isset($identities[$handoff->idempotencyIdentity()])) {
                throw new InvalidArgumentException('A phase handoff cannot be duplicated.');
            }
            $identities[$handoff->idempotencyIdentity()] = true;
        }

        return array_values($handoffs);
    }

    /** @param list<mixed> $tasks @return list<LoopTaskComposition> */
    private function taskCompositions(array $tasks): array
    {
        $identities = [];
        foreach ($tasks as $task) {
            if (! $task instanceof LoopTaskComposition) {
                throw new InvalidArgumentException('Loop tasks must contain LoopTaskComposition values.');
            }
            if (isset($identities[$task->task->id->value])) {
                throw new InvalidArgumentException('A task cannot be duplicated in a Loop composition.');
            }
            $identities[$task->task->id->value] = true;
        }

        return array_values($tasks);
    }
}
