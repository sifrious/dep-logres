<?php

declare(strict_types=1);

namespace Sifrious\Logres;

final readonly class TaskPlanReadModel
{
    public array $tasks;

    public function __construct(
        public string $id,
        public string $requestId,
        array $tasks,
        public ?string $replansFrom,
    ) {
        $this->tasks = array_values($tasks);
    }

    public static function fromPlan(TaskPlan $plan): self
    {
        return new self(
            id: $plan->id->value,
            requestId: $plan->requestId->value,
            tasks: array_map(
                static fn (TranslatedTask $task): array => [
                    'id' => $task->id->value,
                    'objective' => $task->objective,
                    'expected_output' => $task->expectedOutput,
                    'acceptance_evidence' => $task->acceptanceEvidence,
                    'dependencies' => array_map(static fn (TaskId $id): string => $id->value, $task->dependencies),
                    'blocked_by' => array_map(static fn (TaskId $id): string => $id->value, $plan->blockedBy($task->id)),
                    'readiness_conditions' => $task->readinessConditions,
                    'can_run_concurrently' => $task->canRunConcurrently,
                    'may_require_human_input' => $task->mayRequireHumanInput,
                    'target' => $task->target,
                    'agent' => $task->agent,
                    'status' => $task->status->value,
                    'readiness' => $plan->readiness($task->id)->value,
                    'actions' => array_map(static fn (TaskAction $action): string => $action->value, $plan->availableActions($task->id)),
                    'user_actions' => array_map(static fn (TaskAction $action): string => $action->value, $plan->userActions($task->id)),
                ],
                $plan->tasks,
            ),
            replansFrom: $plan->replansFrom?->value,
        );
    }
}
