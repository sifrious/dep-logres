<?php

declare(strict_types=1);

namespace Sifrious\Logres\Tests\Fixtures;

use Sifrious\Logres\ExecutionTargetSelector;
use Sifrious\Logres\ProviderAcknowledgement;
use Sifrious\Logres\ProviderExecutionId;
use Sifrious\Logres\Run;
use Sifrious\Logres\RunId;
use Sifrious\Logres\RunProvenance;
use Sifrious\Logres\TaskPromptCompiler;

final class RunIdentityFixtures
{
    public const CREATED_AT = '2026-08-28T05:40:00Z';

    public const DISPATCHED_AT = '2026-08-28T05:41:00Z';

    public const ACKNOWLEDGED_AT = '2026-08-28T05:41:01Z';

    public static function run(string $id = 'fixture-001'): Run
    {
        $prompt = (new TaskPromptCompiler)->compile(TaskPromptFixtures::input());
        $requirements = ExecutionTargetFixtures::requirements(taskId: $prompt->taskId);
        $selection = (new ExecutionTargetSelector)->select(
            $requirements,
            [ExecutionTargetFixtures::candidate()],
            ExecutionTargetFixtures::authorization(),
            '2026-08-28T05:39:00Z',
        )->selection;

        if ($selection === null) {
            throw new \RuntimeException('The execution-target fixture must select a target.');
        }

        return Run::create(
            new RunId("run:{$id}"),
            RunProvenance::capture(
                prompt: $prompt,
                targetSelection: $selection,
                policyVersions: [
                    'dispatch_authorization' => 'v1',
                    'target_selection' => 'v1',
                ],
                initiatingActor: 'user:mary',
                createdAt: self::CREATED_AT,
            ),
        );
    }

    public static function acknowledgement(string $id = 'execution-001'): ProviderAcknowledgement
    {
        return new ProviderAcknowledgement(
            providerExecutionId: new ProviderExecutionId('orbs', $id),
            targetId: ExecutionTargetFixtures::candidate()->id,
            receivedAt: self::ACKNOWLEDGED_AT,
        );
    }
}
