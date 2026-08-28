<?php

declare(strict_types=1);

namespace Sifrious\Logres;

use InvalidArgumentException;

final readonly class TaskPlan
{
    public array $tasks;

    public function __construct(
        public TaskPlanId $id,
        public ExecutionRequestId $requestId,
        array $tasks,
        public ?TaskPlanId $replansFrom = null,
    ) {
        $this->tasks = array_values($tasks);
    }

    public function task(TaskId $id): TranslatedTask
    {
        foreach ($this->tasks as $task) {
            if ($task->id->value === $id->value) {
                return $task;
            }
        }

        throw new InvalidArgumentException("Task {$id->value} is not in plan {$this->id->value}.");
    }

    public function readiness(TaskId $id): TaskReadiness
    {
        $task = $this->task($id);

        if ($task->status !== TaskStatus::Planned) {
            return TaskReadiness::from($task->status->value);
        }

        return $this->blockedBy($id) === [] ? TaskReadiness::Ready : TaskReadiness::Blocked;
    }

    public function blockedBy(TaskId $id): array
    {
        $task = $this->task($id);

        return array_values(array_filter(
            $task->dependencies,
            fn (TaskId $dependency): bool => ! in_array(
                $this->task($dependency)->status,
                [TaskStatus::Succeeded, TaskStatus::Skipped],
                true,
            ),
        ));
    }

    public function start(TaskId $id, TaskStartAuthority $authority): self
    {
        if ($this->readiness($id) !== TaskReadiness::Ready) {
            throw new InvalidTaskTransition("Task {$id->value} is not ready to start with {$authority->value} authority.");
        }

        return $this->replace($this->task($id)->withStatus(TaskStatus::Running));
    }

    public function transition(TaskId $id, TaskStatus $status): self
    {
        $task = $this->task($id);
        $allowed = match ($task->status) {
            TaskStatus::Planned => [TaskStatus::Skipped, TaskStatus::Canceled],
            TaskStatus::Running => [TaskStatus::WaitingForInput, TaskStatus::Succeeded, TaskStatus::Failed, TaskStatus::Canceled],
            TaskStatus::WaitingForInput, TaskStatus::Failed => [TaskStatus::Planned, TaskStatus::Canceled],
            TaskStatus::Succeeded, TaskStatus::Skipped, TaskStatus::Canceled => [],
        };

        if (! in_array($status, $allowed, true)) {
            throw new InvalidTaskTransition("Task {$id->value} cannot transition from {$task->status->value} to {$status->value}.");
        }

        return $this->replace($task->withStatus($status));
    }

    public function apply(TaskId $id, TaskAction $action, ?TaskStartAuthority $authority = null): self
    {
        if (! in_array($action, $this->availableActions($id), true)) {
            throw new InvalidTaskTransition("Action {$action->value} is not available for task {$id->value}.");
        }

        if ($action === TaskAction::Start) {
            if ($authority === null) {
                throw new InvalidTaskTransition("Starting task {$id->value} requires explicit authority.");
            }

            return $this->start($id, $authority);
        }

        $status = match ($action) {
            TaskAction::Skip => TaskStatus::Skipped,
            TaskAction::Cancel => TaskStatus::Canceled,
            TaskAction::Succeed => TaskStatus::Succeeded,
            TaskAction::Fail => TaskStatus::Failed,
            TaskAction::WaitForInput => TaskStatus::WaitingForInput,
            TaskAction::Retry => TaskStatus::Planned,
            TaskAction::Replan => throw new InvalidTaskTransition("Action {$action->value} does not map to a task status."),
        };

        return $this->transition($id, $status);
    }

    public function availableActions(TaskId $id): array
    {
        $task = $this->task($id);

        return match ($task->status) {
            TaskStatus::Planned => array_values(array_filter([
                $this->readiness($id) === TaskReadiness::Ready ? TaskAction::Start : null,
                TaskAction::Skip,
                TaskAction::Cancel,
            ])),
            TaskStatus::Running => [TaskAction::Succeed, TaskAction::Fail, TaskAction::WaitForInput, TaskAction::Cancel],
            TaskStatus::WaitingForInput => [TaskAction::Retry, TaskAction::Cancel],
            TaskStatus::Failed => [TaskAction::Retry, TaskAction::Replan, TaskAction::Cancel],
            TaskStatus::Succeeded, TaskStatus::Skipped, TaskStatus::Canceled => [],
        };
    }

    public function userActions(TaskId $id): array
    {
        return array_values(array_filter(
            $this->availableActions($id),
            static fn (TaskAction $action): bool => in_array(
                $action,
                [TaskAction::Skip, TaskAction::Cancel, TaskAction::Retry, TaskAction::Replan],
                true,
            ),
        ));
    }

    public function replanned(TaskPlanId $id, array $tasks): self
    {
        return new self($id, $this->requestId, $tasks, $this->id);
    }

    private function replace(TranslatedTask $replacement): self
    {
        return new self(
            id: $this->id,
            requestId: $this->requestId,
            tasks: array_map(
                static fn (TranslatedTask $task): TranslatedTask => $task->id->value === $replacement->id->value ? $replacement : $task,
                $this->tasks,
            ),
            replansFrom: $this->replansFrom,
        );
    }
}
