<?php

declare(strict_types=1);

namespace Sifrious\Logres\Tests\Fixtures;

use Sifrious\Logres\ExecutionTargetAuthorization;
use Sifrious\Logres\ExecutionTargetCandidate;
use Sifrious\Logres\ExecutionTargetId;
use Sifrious\Logres\ExecutionTargetRequirements;
use Sifrious\Logres\TargetAvailability;
use Sifrious\Logres\TargetHealth;
use Sifrious\Logres\TaskId;

final class ExecutionTargetFixtures
{
    public static function requirements(array $capabilities = ['git', 'php'], ?TaskId $taskId = null): ExecutionTargetRequirements
    {
        return new ExecutionTargetRequirements(
            taskId: $taskId ?? new TaskId('task:accepted-fixture:inspect'),
            provider: 'orbs',
            workspaceAuthority: 'workspace:personal',
            repositoryIdentity: 'repository:atlas',
            agentAdapter: 'codex',
            capabilities: $capabilities,
        );
    }

    public static function candidate(
        string $id = 'orb-a',
        TargetAvailability $availability = TargetAvailability::Available,
        TargetHealth $health = TargetHealth::Healthy,
        array $capabilities = ['git', 'php'],
        array $agentAdapters = ['amp', 'codex'],
        ?TaskId $currentTaskId = null,
    ): ExecutionTargetCandidate {
        return new ExecutionTargetCandidate(
            id: new ExecutionTargetId("target:orbs:{$id}"),
            provider: 'orbs',
            availability: $availability,
            health: $health,
            runtime: 'debian-12:a1.small',
            workspaceAuthority: 'workspace:personal',
            repositoryIdentity: 'repository:atlas',
            agentAdapters: $agentAdapters,
            capabilities: $capabilities,
            currentTaskId: $currentTaskId,
            observedAt: '2026-08-28T04:30:00Z',
        );
    }

    public static function authorization(array $targetIds = ['target:orbs:orb-a']): ExecutionTargetAuthorization
    {
        return new ExecutionTargetAuthorization(
            callerIdentity: 'user:mary',
            targetIds: $targetIds,
            workspaceAuthorities: ['workspace:personal'],
            repositoryIdentities: ['repository:atlas'],
        );
    }
}
