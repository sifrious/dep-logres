<?php

declare(strict_types=1);

namespace Sifrious\Logres\Tests\Fixtures;

use Sifrious\Logres\ExecutionTargetSelector;
use Sifrious\Logres\DispatchAuthorizationPolicy;
use Sifrious\Logres\ExecutionGrant;
use Sifrious\Logres\ExecutionTargetId;
use Sifrious\Logres\ProviderAcknowledgement;
use Sifrious\Logres\ProviderExecutionId;
use Sifrious\Logres\Run;
use Sifrious\Logres\RunId;
use Sifrious\Logres\RunProvenance;
use Sifrious\Logres\RepositoryIdentity;
use Sifrious\Logres\TaskPromptCompiler;
use Sifrious\Logres\WorkspaceAuthority;
use Sifrious\Logres\WorkspacePath;
use Sifrious\Logres\StacksExecutionContext;
use Sifrious\StacksContract\ExecutionProvenance;
use Sifrious\StacksContract\WorkspaceReference;

final class RunIdentityFixtures
{
    public const CREATED_AT = '2026-08-28T05:40:00Z';

    public const DISPATCHED_AT = '2026-08-28T05:41:00Z';

    public const ACKNOWLEDGED_AT = '2026-08-28T05:41:01Z';

    public static function run(string $id = 'fixture-001'): Run
    {
        $run = self::unauthorizedRun($id);
        $decision = (new DispatchAuthorizationPolicy)->authorize(
            $run,
            self::grant(),
            WorkspacePath::fromInput('/workspace/atlas'),
            [new RepositoryIdentity('repository:atlas')],
            'production',
            self::CREATED_AT,
        );

        return $run->authorized($decision);
    }

    public static function unauthorizedRun(string $id = 'fixture-001'): Run
    {
        $prompt = (new TaskPromptCompiler)->compile(TaskPromptFixtures::input());
        $requirements = ExecutionTargetFixtures::requirements(taskId: $prompt->taskId);
        $selection = (new ExecutionTargetSelector)->select(
            $requirements,
            [ExecutionTargetFixtures::candidate(observedAt: '2026-08-28T05:39:00Z')],
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
                executionIdentity: self::executionIdentity(),
            ),
        );
    }

    public static function grant(
        ?ExecutionTargetId $targetId = null,
        ?RepositoryIdentity $repositoryIdentity = null,
        ?WorkspaceAuthority $workspaceAuthority = null,
        ?WorkspacePath $workspaceRoot = null,
        string $environment = 'production',
        string $runtime = 'debian-12:a1.small',
        array $permissions = ['filesystem:read'],
        string $issuedAt = '2026-08-28T05:00:00Z',
        string $expiresAt = '2026-08-28T07:00:00Z',
    ): ExecutionGrant {
        return new ExecutionGrant(
            id: 'grant:fixture',
            actor: 'user:mary',
            targetId: $targetId ?? new ExecutionTargetId('target:orbs:orb-a'),
            repositoryIdentity: $repositoryIdentity ?? new RepositoryIdentity('repository:atlas'),
            workspaceAuthority: $workspaceAuthority ?? new WorkspaceAuthority('workspace:personal'),
            workspaceRoot: $workspaceRoot ?? WorkspacePath::fromInput('/workspace/atlas'),
            environment: $environment,
            runtime: $runtime,
            permissions: $permissions,
            policyVersion: 'dispatch-authorization-v1',
            issuedAt: $issuedAt,
            expiresAt: $expiresAt,
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

    public static function executionIdentity(): StacksExecutionContext
    {
        $workspace = new WorkspaceReference(
            'workspace:personal',
            'repository:atlas',
            'github.com/sifrious/atlas',
            'worktree:atlas:personal',
            'worktree',
            'available',
            '/workspace/atlas',
            'main',
            str_repeat('a', 40),
        );

        return StacksExecutionContext::capture(
            $workspace,
            new ExecutionProvenance(
                'workspace:personal',
                'repository:atlas',
                'github.com/sifrious/atlas',
                'worktree:atlas:personal',
                'worktree',
                '/workspace/atlas',
                str_repeat('a', 40),
                'main',
                'git@github.com:sifrious/atlas.git',
                '2026-08-28T05:39:00+00:00',
            ),
            str_repeat('a', 40),
            'git-worktree:worktree:atlas:personal',
            'capability-snapshot:'.str_repeat('c', 64),
            'target:orbs:orb-a',
        );
    }
}
