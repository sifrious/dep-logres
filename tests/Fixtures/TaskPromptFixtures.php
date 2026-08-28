<?php

declare(strict_types=1);

namespace Sifrious\Logres\Tests\Fixtures;

use Sifrious\Logres\TaskPromptCompilationInput;
use Sifrious\Logres\TaskPromptContextSource;
use Sifrious\Logres\TaskPromptReportingContract;
use Sifrious\Logres\TaskPromptResultContract;
use Sifrious\Logres\TaskPromptSkill;
use Sifrious\Logres\TaskPromptTool;

final class TaskPromptFixtures
{
    public static function input(string $instructionSuffix = ''): TaskPromptCompilationInput
    {
        $request = ExecutionRequestFixtures::accepted();
        $task = TaskPlanFixtures::fourTasks()->tasks[0];

        return new TaskPromptCompilationInput(
            request: $request,
            task: $task,
            prerequisiteOutputs: [],
            contextSources: [
                new TaskPromptContextSource(
                    id: 'file:AGENTS.md',
                    kind: 'project_instruction',
                    label: 'Project agent instructions',
                    content: "Build the smallest coherent capability.{$instructionSuffix}",
                ),
                new TaskPromptContextSource(
                    id: 'repository:atlas-api',
                    kind: 'repository',
                    label: 'Atlas API repository',
                    content: 'commit:abc123',
                ),
            ],
            projectInstructions: 'Follow the Programming Manifesto and preserve current behavior.',
            skills: [new TaskPromptSkill('landing-research', '1', str_repeat('a', 64))],
            tools: [new TaskPromptTool('filesystem', 'Read repository files')],
            allowedOperations: ['filesystem:read'],
            resultContract: new TaskPromptResultContract('structured', ['summary', 'artifacts', 'evidence']),
            reportingContract: new TaskPromptReportingContract(['started', 'progress', 'completed', 'failed'], 'request_input'),
        );
    }
}
