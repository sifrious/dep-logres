<?php

declare(strict_types=1);

namespace Sifrious\Logres\Tests;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sifrious\Logres\CheckDisposition;
use Sifrious\Logres\CheckResult;
use Sifrious\Logres\DeliberationOrigin;
use Sifrious\Logres\EvidenceReference;
use Sifrious\Logres\ExecutionRequest;
use Sifrious\Logres\FinalizationStatus;
use Sifrious\Logres\LoopCheckpoint;
use Sifrious\Logres\LoopCheckpointType;
use Sifrious\Logres\LoopComposition;
use Sifrious\Logres\LoopDisposition;
use Sifrious\Logres\LoopExternalWorkReference;
use Sifrious\Logres\LoopHandoffReference;
use Sifrious\Logres\LoopHandoffType;
use Sifrious\Logres\LoopTaskComposition;
use Sifrious\Logres\RequiredVerificationOutcome;
use Sifrious\Logres\RunEvidence;
use Sifrious\Logres\RunResult;
use Sifrious\Logres\RunStatus;
use Sifrious\Logres\TaskPlan;
use Sifrious\Logres\TaskStatus;
use Sifrious\Logres\VerificationStatus;
use Sifrious\Logres\VerifiedOutcome;
use Sifrious\Logres\Tests\Fixtures\ExecutionRequestFixtures;
use Sifrious\Logres\Tests\Fixtures\RunIdentityFixtures;
use Sifrious\Logres\Tests\Fixtures\TaskPlanFixtures;

final class LoopCompositionTest extends TestCase
{
    #[Test]
    public function zero_work_deliberation_completes_with_a_durable_decision_and_no_plan_or_run(): void
    {
        $loop = new LoopComposition(
            request: $this->request(materialized: false),
            decisionReferences: ['decision:no-work'],
            checkpoints: [],
            plan: null,
            phaseHandoffs: [],
            tasks: [],
            zeroWorkDecisionReference: 'decision:no-work',
        );

        self::assertSame('input:feature-discussion', $loop->request->origin?->userInputReference);
        self::assertNull($loop->plan);
        self::assertSame([], $loop->tasks);
        self::assertSame(LoopDisposition::Complete, $loop->determination->disposition);
        self::assertSame('decision:no-work', $loop->determination->decisionReference);
    }

    #[Test]
    public function one_ticket_preserves_the_causal_path_through_verified_evidence(): void
    {
        $task = TaskPlanFixtures::fourTasks()->tasks[0]->withStatus(TaskStatus::Succeeded);
        $plan = new TaskPlan(TaskPlanFixtures::fourTasks()->id, $task->requestId, [$task]);
        $handoff = $this->ticketHandoff($task->id->value);
        $external = new LoopExternalWorkReference(
            $task->id,
            'linear',
            'MME-2273',
            'authorization:linear-write',
            'linear:create:task-inspect',
        );
        [$result, $verification] = $this->verifiedResult(withEvidence: true);

        $loop = new LoopComposition(
            request: $this->request(),
            decisionReferences: ['decision:architecture', 'decision:scope-cut'],
            checkpoints: $this->checkpoints(),
            plan: $plan,
            phaseHandoffs: [$this->phaseHandoff($plan->id->value)],
            tasks: [new LoopTaskComposition(
                $task,
                $handoff,
                $external,
                RunIdentityFixtures::run(),
                $result,
                $verification,
            )],
        );

        self::assertSame(LoopDisposition::Complete, $loop->determination->disposition);
        self::assertSame('input:feature-discussion', $loop->request->origin?->userInputReference);
        self::assertSame('decision:scope-cut', $loop->checkpoints[1]->decisionReference);
        self::assertSame($plan->id->value, $loop->phaseHandoffs[0]->originReference);
        self::assertSame($task->id->value, $loop->tasks[0]->handoff?->taskId?->value);
        self::assertSame('linear:create:task-inspect', $loop->tasks[0]->externalWork?->idempotencyIdentity);
        self::assertSame('run:fixture-001', $loop->tasks[0]->run?->id->value);
        self::assertSame('test_execution', $loop->tasks[0]->verification?->evidence[0]->kind);
    }

    #[Test]
    public function multi_ticket_composition_uses_explicit_dependencies_not_composition_position(): void
    {
        $plan = TaskPlanFixtures::fourTasks();
        $tasks = array_map(
            fn ($task): LoopTaskComposition => new LoopTaskComposition($task, $this->ticketHandoff($task->id->value)),
            array_reverse($plan->tasks),
        );

        $loop = new LoopComposition(
            request: $this->request(),
            decisionReferences: ['decision:architecture', 'decision:scope-cut'],
            checkpoints: array_reverse($this->checkpoints()),
            plan: $plan,
            phaseHandoffs: [$this->phaseHandoff($plan->id->value)],
            tasks: $tasks,
        );

        self::assertSame(LoopDisposition::Advance, $loop->determination->disposition);
        self::assertSame(
            ['task:inspect', 'task:define'],
            array_map(static fn ($id): string => $id->value, $plan->task($plan->tasks[2]->id)->dependencies),
        );
        self::assertSame('task:verify', $loop->tasks[0]->task->id->value);
    }

    #[Test]
    public function failed_validation_reenters_the_owning_task(): void
    {
        $task = TaskPlanFixtures::fourTasks()->tasks[0]->withStatus(TaskStatus::Succeeded);
        $plan = new TaskPlan(TaskPlanFixtures::fourTasks()->id, $task->requestId, [$task]);
        [$result, $verification] = $this->failedVerification();

        $loop = $this->materializedLoop(
            $plan,
            [new LoopTaskComposition($task, $this->ticketHandoff($task->id->value), run: RunIdentityFixtures::run(), result: $result, verification: $verification)],
        );

        self::assertSame(LoopDisposition::Rework, $loop->determination->disposition);
        self::assertSame($task->id->value, $loop->determination->taskId?->value);
        self::assertSame('run:fixture-001', $loop->tasks[0]->run?->id->value);
    }

    #[Test]
    public function successful_child_status_without_required_evidence_does_not_complete_the_loop(): void
    {
        $task = TaskPlanFixtures::fourTasks()->tasks[0]->withStatus(TaskStatus::Succeeded);
        $plan = new TaskPlan(TaskPlanFixtures::fourTasks()->id, $task->requestId, [$task]);
        [$result, $verification] = $this->verifiedResult(withEvidence: false);

        $loop = $this->materializedLoop(
            $plan,
            [new LoopTaskComposition($task, $this->ticketHandoff($task->id->value), run: RunIdentityFixtures::run(), result: $result, verification: $verification)],
        );

        self::assertSame(LoopDisposition::Advance, $loop->determination->disposition);
        self::assertFalse($loop->tasks[0]->hasVerifiedEvidence());
    }

    #[Test]
    public function replay_projects_the_same_existing_objects_and_idempotency_identities(): void
    {
        $plan = TaskPlanFixtures::fourTasks();
        $tasks = array_map(
            fn ($task): LoopTaskComposition => new LoopTaskComposition(
                $task,
                $this->ticketHandoff($task->id->value),
                new LoopExternalWorkReference(
                    $task->id,
                    'linear',
                    'ticket:'.$task->id->value,
                    'authorization:linear-write',
                    'linear:create:'.$task->id->value,
                ),
            ),
            $plan->tasks,
        );

        $first = $this->materializedLoop($plan, $tasks);
        $replay = $this->materializedLoop($plan, $tasks);

        self::assertEquals($first, $replay);
        self::assertSame($plan, $replay->plan);
        self::assertSame(
            $first->tasks[0]->handoff?->idempotencyIdentity(),
            $replay->tasks[0]->handoff?->idempotencyIdentity(),
        );
    }

    #[Test]
    public function materialization_is_rejected_until_both_deliberation_checkpoints_exist(): void
    {
        $plan = TaskPlanFixtures::fourTasks();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Architecture placement and simplicity cut');

        new LoopComposition(
            request: $this->request(),
            decisionReferences: ['decision:architecture'],
            checkpoints: [new LoopCheckpoint(LoopCheckpointType::ArchitecturePlacement, 'decision:architecture', 1)],
            plan: $plan,
            phaseHandoffs: [],
            tasks: array_map(static fn ($task): LoopTaskComposition => new LoopTaskComposition($task), $plan->tasks),
        );
    }

    private function request(bool $materialized = true): ExecutionRequest
    {
        $request = ExecutionRequestFixtures::accepted();

        return new ExecutionRequest(
            id: $request->id,
            originalPrompt: $request->originalPrompt,
            context: $request->context,
            desiredResult: $request->desiredResult,
            attachments: $request->attachments,
            constraints: $request->constraints,
            permissions: $request->permissions,
            authorization: $request->authorization,
            channel: $request->channel,
            relationship: $request->relationship,
            parentRequestId: $request->parentRequestId,
            origin: new DeliberationOrigin(
                'input:feature-discussion',
                'intent:parser-fix',
                'conversation:architecture-review',
                $materialized ? 'plan:accepted-fixture' : null,
            ),
        );
    }

    /** @return list<LoopCheckpoint> */
    private function checkpoints(): array
    {
        return [
            new LoopCheckpoint(LoopCheckpointType::ArchitecturePlacement, 'decision:architecture', 1),
            new LoopCheckpoint(LoopCheckpointType::SimplicityCut, 'decision:scope-cut', 2),
        ];
    }

    private function phaseHandoff(string $planId): LoopHandoffReference
    {
        return new LoopHandoffReference(
            LoopHandoffType::Phase,
            'artifact:phase-handoff',
            $planId,
            hash('sha256', 'phase-handoff'),
        );
    }

    private function ticketHandoff(string $taskId): LoopHandoffReference
    {
        return new LoopHandoffReference(
            LoopHandoffType::Ticket,
            'artifact:handoff:'.$taskId,
            $taskId,
            hash('sha256', 'handoff:'.$taskId),
            new \Sifrious\Logres\TaskId($taskId),
        );
    }

    /** @return array{RunResult, VerifiedOutcome} */
    private function verifiedResult(bool $withEvidence): array
    {
        $evidence = $withEvidence
            ? [new EvidenceReference('test_execution', 'suite:Loop#passes', '2026-09-04T12:00:00Z', 1)]
            : [];

        return [
            new RunResult(
                RunStatus::Succeeded,
                exitCode: 0,
                evidence: [new RunEvidence('result.report', 'artifact:result-report', '2026-09-04T12:00:00Z')],
                requiredVerification: RequiredVerificationOutcome::Passed,
                verificationStatus: VerificationStatus::Succeeded,
                finalizationStatus: FinalizationStatus::Complete,
            ),
            new VerifiedOutcome(
                RequiredVerificationOutcome::Passed,
                VerificationStatus::Succeeded,
                'Required checks passed.',
                [new CheckResult('loop', 'Loop acceptance', true, CheckDisposition::Passed, $evidence)],
                $evidence,
            ),
        ];
    }

    /** @return array{RunResult, VerifiedOutcome} */
    private function failedVerification(): array
    {
        $evidence = [new EvidenceReference('test_execution', 'suite:Loop#fails', '2026-09-04T12:00:00Z', 1)];

        return [
            new RunResult(
                RunStatus::Failed,
                exitCode: 1,
                evidence: [new RunEvidence('result.report', 'artifact:failed-report', '2026-09-04T12:00:00Z')],
                requiredVerification: RequiredVerificationOutcome::Failed,
                verificationStatus: VerificationStatus::Failed,
                finalizationStatus: FinalizationStatus::Complete,
            ),
            new VerifiedOutcome(
                RequiredVerificationOutcome::Failed,
                VerificationStatus::Failed,
                'Required check failed.',
                [new CheckResult('loop', 'Loop acceptance', true, CheckDisposition::Failed, $evidence)],
                $evidence,
            ),
        ];
    }

    /** @param list<LoopTaskComposition> $tasks */
    private function materializedLoop(TaskPlan $plan, array $tasks): LoopComposition
    {
        return new LoopComposition(
            request: $this->request(),
            decisionReferences: ['decision:architecture', 'decision:scope-cut'],
            checkpoints: $this->checkpoints(),
            plan: $plan,
            phaseHandoffs: [$this->phaseHandoff($plan->id->value)],
            tasks: $tasks,
        );
    }
}
