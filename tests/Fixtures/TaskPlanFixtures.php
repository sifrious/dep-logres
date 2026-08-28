<?php

declare(strict_types=1);

namespace Sifrious\Logres\Tests\Fixtures;

use Sifrious\Logres\ExecutionRequestId;
use Sifrious\Logres\TaskId;
use Sifrious\Logres\TaskPlan;
use Sifrious\Logres\TaskPlanId;
use Sifrious\Logres\TaskStatus;
use Sifrious\Logres\TranslatedTask;

final class TaskPlanFixtures
{
    public static function fourTasks(): TaskPlan
    {
        $requestId = new ExecutionRequestId('request:accepted-fixture');

        return new TaskPlan(
            new TaskPlanId('plan:accepted-fixture'),
            $requestId,
            [
                self::task('task:inspect', 'Inspect the current architecture.', [], true, false, ['Current behavior is recorded.']),
                self::task('task:define', 'Define the intended workflow.', [], true, true, ['The workflow and decisions are explicit.']),
                self::task('task:implement', 'Implement the smallest coherent change.', ['task:inspect', 'task:define'], false, false, ['The requested behavior is demonstrable.']),
                self::task('task:verify', 'Verify and summarize the change.', ['task:implement'], false, false, ['Relevant checks pass.', 'Remaining failures are recorded.']),
            ],
        );
    }

    public static function invalidCycle(): TaskPlan
    {
        return new TaskPlan(
            new TaskPlanId('plan:cycle'),
            new ExecutionRequestId('request:accepted-fixture'),
            [
                self::task('task:first', 'First cyclic task.', ['task:second'], false, false, ['First evidence.']),
                self::task('task:second', 'Second cyclic task.', ['task:first'], false, false, ['Second evidence.']),
            ],
        );
    }

    private static function task(
        string $id,
        string $objective,
        array $dependencies,
        bool $concurrent,
        bool $humanInput,
        array $evidence,
    ): TranslatedTask {
        return new TranslatedTask(
            id: new TaskId($id),
            requestId: new ExecutionRequestId('request:accepted-fixture'),
            objective: $objective,
            expectedOutput: 'A durable project artifact.',
            acceptanceEvidence: $evidence,
            dependencies: array_map(static fn (string $dependency): TaskId => new TaskId($dependency), $dependencies),
            readinessConditions: ['Required project context is available.'],
            canRunConcurrently: $concurrent,
            mayRequireHumanInput: $humanInput,
            target: 'repository:atlas-api',
            agent: 'codex',
            status: TaskStatus::Planned,
        );
    }
}
