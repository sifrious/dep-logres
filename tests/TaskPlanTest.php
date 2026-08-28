<?php

declare(strict_types=1);

namespace Sifrious\Logres\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sifrious\Logres\DeterministicTaskPlanner;
use Sifrious\Logres\InvalidTaskTransition;
use Sifrious\Logres\TaskId;
use Sifrious\Logres\TaskAction;
use Sifrious\Logres\TaskPlanId;
use Sifrious\Logres\TaskPlanReadModel;
use Sifrious\Logres\TaskPlanValidator;
use Sifrious\Logres\TaskReadiness;
use Sifrious\Logres\TaskStartAuthority;
use Sifrious\Logres\TaskStatus;
use Sifrious\Logres\Tests\Fixtures\ExecutionRequestFixtures;
use Sifrious\Logres\Tests\Fixtures\TaskPlanFixtures;

final class TaskPlanTest extends TestCase
{
    #[Test]
    public function four_task_fixture_exposes_parallel_ready_work_and_blocked_dependencies(): void
    {
        $plan = TaskPlanFixtures::fourTasks();

        self::assertSame([], (new TaskPlanValidator)->validate($plan));
        self::assertCount(4, $plan->tasks);
        self::assertSame(TaskReadiness::Ready, $plan->readiness(new TaskId('task:inspect')));
        self::assertSame(TaskReadiness::Ready, $plan->readiness(new TaskId('task:define')));
        self::assertSame(TaskReadiness::Blocked, $plan->readiness(new TaskId('task:implement')));
        self::assertTrue($plan->task(new TaskId('task:inspect'))->canRunConcurrently);
        self::assertTrue($plan->task(new TaskId('task:define'))->mayRequireHumanInput);
        self::assertSame(['Current behavior is recorded.'], $plan->task(new TaskId('task:inspect'))->acceptanceEvidence);
    }

    #[Test]
    public function invalid_cycle_is_rejected(): void
    {
        $failures = (new TaskPlanValidator)->validate(TaskPlanFixtures::invalidCycle());

        self::assertSame(['dependency_cycle'], array_map(static fn ($failure): string => $failure->code, $failures));
    }

    #[Test]
    public function dependency_completion_and_skip_make_blocked_work_ready(): void
    {
        $plan = TaskPlanFixtures::fourTasks()
            ->start(new TaskId('task:inspect'), TaskStartAuthority::Automatic)
            ->transition(new TaskId('task:inspect'), TaskStatus::Succeeded)
            ->transition(new TaskId('task:define'), TaskStatus::Skipped);

        self::assertSame(TaskReadiness::Ready, $plan->readiness(new TaskId('task:implement')));
        self::assertSame(TaskStatus::Succeeded, $plan->task(new TaskId('task:inspect'))->status);
        self::assertSame(TaskStatus::Skipped, $plan->task(new TaskId('task:define'))->status);
    }

    #[Test]
    public function canceled_or_failed_dependencies_do_not_make_dependents_ready(): void
    {
        $canceled = TaskPlanFixtures::fourTasks()
            ->transition(new TaskId('task:inspect'), TaskStatus::Canceled)
            ->transition(new TaskId('task:define'), TaskStatus::Skipped);
        $failed = TaskPlanFixtures::fourTasks()
            ->start(new TaskId('task:inspect'), TaskStartAuthority::Manual)
            ->transition(new TaskId('task:inspect'), TaskStatus::Failed)
            ->transition(new TaskId('task:define'), TaskStatus::Skipped);

        self::assertSame(TaskReadiness::Blocked, $canceled->readiness(new TaskId('task:implement')));
        self::assertSame(TaskReadiness::Blocked, $failed->readiness(new TaskId('task:implement')));
        self::assertSame(TaskStatus::Planned, $failed->transition(new TaskId('task:inspect'), TaskStatus::Planned)->task(new TaskId('task:inspect'))->status);
    }

    #[Test]
    public function a_blocked_task_cannot_start_and_terminal_tasks_cannot_transition(): void
    {
        $this->expectException(InvalidTaskTransition::class);

        TaskPlanFixtures::fourTasks()->start(new TaskId('task:implement'), TaskStartAuthority::Automatic);
    }

    #[Test]
    public function available_actions_and_explicit_authority_are_package_owned(): void
    {
        $plan = TaskPlanFixtures::fourTasks();

        self::assertSame(
            [TaskAction::Start, TaskAction::Skip, TaskAction::Cancel],
            $plan->availableActions(new TaskId('task:inspect')),
        );
        self::assertSame(
            [TaskAction::Skip, TaskAction::Cancel],
            $plan->availableActions(new TaskId('task:implement')),
        );

        $this->expectException(InvalidTaskTransition::class);
        $plan->apply(new TaskId('task:inspect'), TaskAction::Start);
    }

    #[Test]
    public function failed_tasks_expose_retry_and_replan_and_new_plans_preserve_lineage(): void
    {
        $failed = TaskPlanFixtures::fourTasks()
            ->start(new TaskId('task:inspect'), TaskStartAuthority::Manual)
            ->apply(new TaskId('task:inspect'), TaskAction::Fail);
        $replanned = $failed->replanned(new TaskPlanId('plan:accepted-fixture:v2'), $failed->tasks);

        self::assertSame(
            [TaskAction::Retry, TaskAction::Replan, TaskAction::Cancel],
            $failed->availableActions(new TaskId('task:inspect')),
        );
        self::assertSame('plan:accepted-fixture', $replanned->replansFrom?->value);
    }

    #[Test]
    public function deterministic_planner_produces_stable_valid_tasks_for_a_request(): void
    {
        $planner = new DeterministicTaskPlanner(new TaskPlanValidator);
        $first = $planner->plan(ExecutionRequestFixtures::accepted());
        $second = $planner->plan(ExecutionRequestFixtures::accepted());

        self::assertTrue($first->acceptedSuccessfully());
        self::assertEquals($first->plan, $second->plan);
        self::assertSame('plan:accepted-fixture:v1', $first->plan?->id->value);
        self::assertSame('repository:atlas-api', $first->plan?->tasks[0]->target);
    }

    #[Test]
    public function deterministic_replanning_advances_identity_and_preserves_plan_lineage(): void
    {
        $planner = new DeterministicTaskPlanner(new TaskPlanValidator);
        $request = ExecutionRequestFixtures::accepted();
        $first = $planner->plan($request)->plan;
        self::assertNotNull($first);

        $second = $planner->replan($request, $first)->plan;

        self::assertSame('plan:accepted-fixture:v2', $second?->id->value);
        self::assertSame('plan:accepted-fixture:v1', $second?->replansFrom?->value);
    }

    #[Test]
    public function read_model_contains_package_computed_readiness_and_connections(): void
    {
        $model = TaskPlanReadModel::fromPlan(TaskPlanFixtures::fourTasks());

        self::assertSame('plan:accepted-fixture', $model->id);
        self::assertSame('ready', $model->tasks[0]['readiness']);
        self::assertSame(['task:inspect', 'task:define'], $model->tasks[2]['dependencies']);
        self::assertSame(['task:inspect', 'task:define'], $model->tasks[2]['blocked_by']);
        self::assertSame(['skip', 'cancel'], $model->tasks[2]['actions']);
        self::assertSame(['skip', 'cancel'], $model->tasks[2]['user_actions']);
    }
}
