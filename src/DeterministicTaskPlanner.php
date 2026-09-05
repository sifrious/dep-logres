<?php

declare(strict_types=1);

namespace Sifrious\Logres;

final readonly class DeterministicTaskPlanner implements TaskPlanner
{
    public function __construct(private TaskPlanValidator $validator) {}

    public function plan(ExecutionRequest $request): TaskPlanningResult
    {
        $suffix = substr($request->id->value, strlen('request:'));
        return $this->result($request, $suffix, 1);
    }

    public function replan(ExecutionRequest $request, TaskPlan $previous): TaskPlanningResult
    {
        $suffix = substr($request->id->value, strlen('request:'));
        preg_match('/:v(\d+)$/', $previous->id->value, $matches);
        $version = isset($matches[1]) ? (int) $matches[1] + 1 : 2;

        return $this->result($request, $suffix, $version, $previous->id);
    }

    private function result(
        ExecutionRequest $request,
        string $suffix,
        int $version,
        ?TaskPlanId $replansFrom = null,
    ): TaskPlanningResult {
        $target = $request->context->repositoryReference ?? $request->context->projectReference ?? 'request:'.$suffix;
        $id = static fn (string $name): TaskId => new TaskId("task:{$suffix}:{$name}");
        $tasks = [
            new TranslatedTask(
                id: $id('inspect'),
                requestId: $request->id,
                objective: "Inspect current behavior and constraints for {$target}.",
                expectedOutput: 'A current-state record tied to the execution request.',
                acceptanceEvidence: ['Relevant existing behavior, constraints, and risks are recorded.'],
                dependencies: [],
                readinessConditions: ['The target is locally inspectable.'],
                canRunConcurrently: true,
                mayRequireHumanInput: false,
                target: $target,
                agent: 'codex',
                executionIdentity: $request->executionIdentity,
            ),
            new TranslatedTask(
                id: $id('define'),
                requestId: $request->id,
                objective: 'Define the smallest workflow that satisfies the desired result.',
                expectedOutput: $request->desiredResult,
                acceptanceEvidence: ['Acceptance evidence and unresolved human decisions are explicit.'],
                dependencies: [],
                readinessConditions: ['The original request and desired result are available.'],
                canRunConcurrently: true,
                mayRequireHumanInput: true,
                target: $target,
                agent: 'codex',
                executionIdentity: $request->executionIdentity,
            ),
            new TranslatedTask(
                id: $id('implement'),
                requestId: $request->id,
                objective: 'Implement the smallest coherent capability described by the request.',
                expectedOutput: $request->desiredResult,
                acceptanceEvidence: ['The requested behavior is demonstrable at the owning boundary.'],
                dependencies: [$id('inspect'), $id('define')],
                readinessConditions: ['Current state and intended workflow are complete.'],
                canRunConcurrently: false,
                mayRequireHumanInput: false,
                target: $target,
                agent: 'codex',
                executionIdentity: $request->executionIdentity,
            ),
            new TranslatedTask(
                id: $id('verify'),
                requestId: $request->id,
                objective: 'Verify, review, and summarize the completed capability.',
                expectedOutput: 'Verification evidence and an inspectable completion record.',
                acceptanceEvidence: ['Relevant checks pass.', 'Remaining failures and decisions are recorded.'],
                dependencies: [$id('implement')],
                readinessConditions: ['Implementation is complete.'],
                canRunConcurrently: false,
                mayRequireHumanInput: false,
                target: $target,
                agent: 'codex',
                executionIdentity: $request->executionIdentity,
            ),
        ];
        $plan = new TaskPlan(new TaskPlanId("plan:{$suffix}:v{$version}"), $request->id, $tasks, $replansFrom);
        $failures = $this->validator->validate($plan);

        return $failures === []
            ? new TaskPlanningResult(TaskPlanningStatus::Accepted, $plan)
            : new TaskPlanningResult(TaskPlanningStatus::Rejected, null, $failures);
    }
}
