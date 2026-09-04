<?php

declare(strict_types=1);

namespace Sifrious\Logres;

final class TaskPlanValidator
{
    public function validate(TaskPlan $plan): array
    {
        $failures = [];
        $byId = [];

        if ($plan->tasks === []) {
            $failures[] = new TaskPlanFailure('tasks_required', 'tasks', 'A task plan requires at least one task.');
        }

        foreach ($plan->tasks as $index => $task) {
            if (! $task instanceof TranslatedTask) {
                $failures[] = new TaskPlanFailure('task_invalid', "tasks.{$index}", 'Every plan entry must be a translated task.');

                continue;
            }

            if (isset($byId[$task->id->value])) {
                $failures[] = new TaskPlanFailure('task_identity_duplicate', "tasks.{$index}.id", 'Task identities must be unique within a plan.');
            }

            $byId[$task->id->value] = $task;

            if ($task->requestId->value !== $plan->requestId->value) {
                $failures[] = new TaskPlanFailure('request_mismatch', "tasks.{$index}.request_id", 'Every task must reference the plan request.');
            }
            if ($task->executionIdentity === null || ! $task->executionIdentity->isDispatchable()) {
                $failures[] = new TaskPlanFailure('workspace_provenance_required', "tasks.{$index}.execution_identity", 'Every new task requires complete canonical Stacks workspace provenance.');
            }

            foreach ([['objective', $task->objective], ['expected_output', $task->expectedOutput], ['target', $task->target], ['agent', $task->agent]] as [$field, $value]) {
                if (trim($value) === '') {
                    $failures[] = new TaskPlanFailure("{$field}_required", "tasks.{$index}.{$field}", str_replace('_', ' ', ucfirst($field)).' is required.');
                }
            }

            if ($task->acceptanceEvidence === []) {
                $failures[] = new TaskPlanFailure('acceptance_evidence_required', "tasks.{$index}.acceptance_evidence", 'At least one acceptance-evidence statement is required.');
            }
        }

        foreach ($plan->tasks as $index => $task) {
            if (! $task instanceof TranslatedTask) {
                continue;
            }

            foreach ($task->dependencies as $dependency) {
                if (! $dependency instanceof TaskId || ! isset($byId[$dependency->value])) {
                    $failures[] = new TaskPlanFailure('dependency_unknown', "tasks.{$index}.dependencies", 'Every dependency must identify another task in the plan.');
                } elseif ($dependency->value === $task->id->value) {
                    $failures[] = new TaskPlanFailure('dependency_self_reference', "tasks.{$index}.dependencies", 'A task cannot depend on itself.');
                }
            }
        }

        if ($this->containsCycle($byId)) {
            $failures[] = new TaskPlanFailure('dependency_cycle', 'tasks', 'Task dependencies must form an acyclic graph.');
        }

        return $failures;
    }

    private function containsCycle(array $tasks): bool
    {
        $visiting = [];
        $visited = [];

        $visit = function (string $id) use (&$visit, &$visiting, &$visited, $tasks): bool {
            if (isset($visiting[$id])) {
                return true;
            }

            if (isset($visited[$id]) || ! isset($tasks[$id])) {
                return false;
            }

            $visiting[$id] = true;

            foreach ($tasks[$id]->dependencies as $dependency) {
                if ($dependency instanceof TaskId && $visit($dependency->value)) {
                    return true;
                }
            }

            unset($visiting[$id]);
            $visited[$id] = true;

            return false;
        };

        foreach (array_keys($tasks) as $id) {
            if ($visit($id)) {
                return true;
            }
        }

        return false;
    }
}
