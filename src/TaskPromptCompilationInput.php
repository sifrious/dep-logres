<?php

declare(strict_types=1);

namespace Sifrious\Logres;

use InvalidArgumentException;

final readonly class TaskPromptCompilationInput
{
    public array $prerequisiteOutputs;

    public array $contextSources;

    public array $skills;

    public array $tools;

    public array $allowedOperations;

    public function __construct(
        public ExecutionRequest $request,
        public TranslatedTask $task,
        array $prerequisiteOutputs,
        array $contextSources,
        public string $projectInstructions,
        array $skills,
        array $tools,
        array $allowedOperations,
        public TaskPromptResultContract $resultContract,
        public TaskPromptReportingContract $reportingContract,
    ) {
        if ($request->id->value !== $task->requestId->value) {
            throw new InvalidArgumentException('The task prompt request and task must share an execution request identity.');
        }

        if (trim($projectInstructions) === '' || $allowedOperations === []) {
            throw new InvalidArgumentException('Task prompt compilation requires project instructions and allowed operations.');
        }

        $this->prerequisiteOutputs = array_values($prerequisiteOutputs);
        $this->contextSources = array_values($contextSources);
        $this->skills = array_values($skills);
        $this->tools = array_values($tools);
        $this->allowedOperations = array_values($allowedOperations);
    }

    public function canonicalData(): array
    {
        $request = ExecutionRequestReadModel::fromRequest($this->request);

        return [
            'allowed_operations' => $this->allowedOperations,
            'context_sources' => array_map(static fn (TaskPromptContextSource $source): array => $source->canonicalData(), $this->contextSources),
            'permissions' => $request->permissions,
            'prerequisite_outputs' => array_map(static fn (TaskPromptPrerequisiteOutput $output): array => $output->canonicalData(), $this->prerequisiteOutputs),
            'project_instructions' => $this->projectInstructions,
            'reporting_contract' => $this->reportingContract->canonicalData(),
            'request' => get_object_vars($request),
            'result_contract' => $this->resultContract->canonicalData(),
            'skills' => array_map(static fn (TaskPromptSkill $skill): array => $skill->canonicalData(), $this->skills),
            'task' => [
                'acceptance_evidence' => $this->task->acceptanceEvidence,
                'agent' => $this->task->agent,
                'can_run_concurrently' => $this->task->canRunConcurrently,
                'dependencies' => array_map(static fn (TaskId $id): string => $id->value, $this->task->dependencies),
                'expected_output' => $this->task->expectedOutput,
                'id' => $this->task->id->value,
                'may_require_human_input' => $this->task->mayRequireHumanInput,
                'objective' => $this->task->objective,
                'readiness_conditions' => $this->task->readinessConditions,
                'status' => $this->task->status->value,
                'target' => $this->task->target,
            ],
            'tools' => array_map(static fn (TaskPromptTool $tool): array => $tool->canonicalData(), $this->tools),
        ];
    }
}
