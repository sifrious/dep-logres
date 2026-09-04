<?php

declare(strict_types=1);

namespace Sifrious\Logres\Tests;

use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sifrious\Logres\AttemptId;
use Sifrious\Logres\DelegationAuthorization;
use Sifrious\Logres\DelegationContext;
use Sifrious\Logres\DelegationId;
use Sifrious\Logres\DelegationReadModel;
use Sifrious\Logres\DelegationRequest;
use Sifrious\Logres\DispatchAuthorizationSnapshot;
use Sifrious\Logres\ExecutionRequestId;
use Sifrious\Logres\ExecutionState;
use Sifrious\Logres\ExecutionTargetId;
use Sifrious\Logres\InputRequestReference;
use Sifrious\Logres\OrbisAgentDefinition;
use Sifrious\Logres\RepositoryIdentity;
use Sifrious\Logres\RunId;
use Sifrious\Logres\RunStatus;
use Sifrious\Logres\WorkspaceAuthority;
use Sifrious\Logres\WorkspacePath;

final class DelegationContractTest extends TestCase
{
    #[Test]
    public function shared_fixture_covers_parallel_children_policy_and_needs_input(): void
    {
        $fixture = json_decode(
            file_get_contents(dirname(__DIR__).'/docs/fixtures/delegation-contract-v1.json'),
            true,
            flags: JSON_THROW_ON_ERROR,
        );

        self::assertSame(1, $fixture['schema_version']);
        self::assertCount(2, $fixture['children']);
        self::assertSame(
            ['run:child-a', 'run:child-b'],
            array_column($fixture['children'], 'child_run_id'),
        );
        self::assertSame('needs_input', $fixture['children'][0]['canonical_execution']['status']);
        self::assertSame('input:child-a:1', $fixture['children'][0]['canonical_execution']['input_request_reference']);
        self::assertSame(2, $fixture['children'][1]['authorization']['maximum_concurrent_children']);
        self::assertSame('failed', $fixture['children'][1]['canonical_execution']['status']);
    }

    #[Test]
    public function child_context_can_only_narrow_parent_authority(): void
    {
        $context = DelegationContext::boundedBy(
            $this->parentAuthorization(),
            new WorkspacePath('/workspace/packages/logres'),
            ['repo:read'],
            ['objective' => 'Inspect the execution contract'],
        );

        self::assertSame('repository:logres', $context->repositoryIdentity->value);
        self::assertSame('/workspace/packages/logres', $context->workspacePath->value);
        self::assertSame(['repo:read'], $context->permissions);

        $this->expectException(InvalidArgumentException::class);
        DelegationContext::boundedBy(
            $this->parentAuthorization(),
            new WorkspacePath('/other-repository'),
            ['repo:read', 'repo:admin'],
            [],
        );
    }

    #[Test]
    public function policy_proof_rejects_exhausted_depth_and_concurrency(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new DelegationAuthorization(
            'delegation-decision:denied',
            'agent-run-policy:v1',
            childDepth: 3,
            maximumDepth: 2,
            activeChildrenBefore: 2,
            maximumConcurrentChildren: 2,
            authorizedAt: '2026-09-04T12:00:00Z',
        );
    }

    #[Test]
    public function projection_uses_canonical_child_attempts_and_preserves_parent_edge(): void
    {
        $request = $this->request();
        $child = ExecutionState::create($request->childRunId, new DateTimeImmutable('2026-09-04T12:00:00Z'))
            ->scheduleAttempt(new AttemptId('attempt:child:1'), new DateTimeImmutable('2026-09-04T12:00:01Z'));

        $model = DelegationReadModel::fromCanonicalState($request, $child);

        self::assertSame('run:parent', $model->parentRunId);
        self::assertSame('attempt:parent:4', $model->parentAttemptId);
        self::assertSame('run:child', $model->childRunId);
        self::assertSame('attempt:child:1', $model->currentAttempt['id']);
        self::assertSame($child->attempts[0]->id->value, $model->attempts[0]['id']);
        self::assertSame('agent-run-policy:v1', $model->policyVersion);
    }

    #[Test]
    public function needs_input_is_projected_as_child_evidence_not_a_second_lifecycle(): void
    {
        $request = $this->request();
        $child = new ExecutionState(
            $request->childRunId,
            RunStatus::NeedsInput,
            new DateTimeImmutable('2026-09-04T12:00:00Z'),
        );
        $input = new InputRequestReference('input:child:1', 'Choose the contract version.');

        $model = DelegationReadModel::fromCanonicalState($request, $child, $input);

        self::assertSame('needs_input', $model->status);
        self::assertSame($input->toArray(), $model->needsInput);

        $this->expectException(InvalidArgumentException::class);
        DelegationReadModel::fromCanonicalState($request, $child);
    }

    private function request(): DelegationRequest
    {
        return new DelegationRequest(
            new DelegationId('delegation:inspect-contract'),
            'delegation-operation:parent-step-4',
            new RunId('run:parent'),
            new AttemptId('attempt:parent:4'),
            new RunId('run:child'),
            new ExecutionRequestId('request:child'),
            new OrbisAgentDefinition('agent:contract-reviewer', 'orbis-agent:v3', str_repeat('a', 64)),
            DelegationContext::boundedBy(
                $this->parentAuthorization(),
                new WorkspacePath('/workspace/packages/logres'),
                ['repo:read'],
                ['objective' => 'Inspect the execution contract'],
            ),
            new DelegationAuthorization(
                'delegation-decision:parent-step-4',
                'agent-run-policy:v1',
                childDepth: 2,
                maximumDepth: 3,
                activeChildrenBefore: 1,
                maximumConcurrentChildren: 3,
                authorizedAt: '2026-09-04T12:00:00Z',
            ),
            '2026-09-04T12:00:00Z',
        );
    }

    private function parentAuthorization(): DispatchAuthorizationSnapshot
    {
        return new DispatchAuthorizationSnapshot(
            'grant:parent',
            'actor:owner',
            new ExecutionTargetId('target:orbis:runner-a'),
            new RepositoryIdentity('repository:logres'),
            new WorkspaceAuthority('workspace:logres'),
            new WorkspacePath('/workspace'),
            'production',
            'php-8.3',
            ['repo:read', 'repo:write'],
            'dispatch-policy:v2',
            '2026-09-04T11:00:00Z',
            '2026-09-04T13:00:00Z',
            '2026-09-04T11:59:00Z',
        );
    }
}
