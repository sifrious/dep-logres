<?php

declare(strict_types=1);

namespace Sifrious\Logres;

/**
 * The phone-to-Mac workflow is already a complete execution request. Translate
 * it into one runnable task without inventing an internal project workflow.
 */
final readonly class DirectTaskPlanner implements TaskPlanner
{
    public function __construct(private TaskPlanValidator $validator) {}

    public function plan(ExecutionRequest $request): TaskPlanningResult
    {
        return $this->result($request, 1);
    }

    public function replan(ExecutionRequest $request, TaskPlan $previous): TaskPlanningResult
    {
        preg_match('/:v(\d+)$/', $previous->id->value, $matches);

        return $this->result(
            $request,
            isset($matches[1]) ? (int) $matches[1] + 1 : 2,
            $previous->id,
        );
    }

    private function result(
        ExecutionRequest $request,
        int $version,
        ?TaskPlanId $replansFrom = null,
    ): TaskPlanningResult {
        $suffix = substr($request->id->value, strlen('request:'));
        $target = $request->context->repositoryReference
            ?? $request->context->projectReference
            ?? $request->id->value;
        $task = new TranslatedTask(
            id: new TaskId("task:{$suffix}:execute"),
            requestId: $request->id,
            objective: $request->originalPrompt,
            expectedOutput: $request->desiredResult,
            acceptanceEvidence: [$request->desiredResult],
            dependencies: [],
            readinessConditions: ['The authorized target is available.'],
            canRunConcurrently: false,
            mayRequireHumanInput: false,
            target: $target,
            agent: 'codex',
            executionIdentity: $request->executionIdentity,
        );
        $plan = new TaskPlan(
            new TaskPlanId("plan:{$suffix}:v{$version}"),
            $request->id,
            [$task],
            $replansFrom,
        );
        $failures = $this->validator->validate($plan);

        return $failures === []
            ? new TaskPlanningResult(TaskPlanningStatus::Accepted, $plan)
            : new TaskPlanningResult(TaskPlanningStatus::Rejected, null, $failures);
    }
}
