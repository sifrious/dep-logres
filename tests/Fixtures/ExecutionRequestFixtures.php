<?php

declare(strict_types=1);

namespace Sifrious\Logres\Tests\Fixtures;

use Sifrious\AuthorizationContract\ActorContext;
use Sifrious\AuthorizationContract\ActorKind;
use Sifrious\AuthorizationContract\AuthorizationContext;
use Sifrious\AuthorizationContract\TenantScope;
use Sifrious\Logres\DeliveryChannel;
use Sifrious\Logres\ExecutionAttachment;
use Sifrious\Logres\ExecutionConstraints;
use Sifrious\Logres\ExecutionContext;
use Sifrious\Logres\ExecutionPermissions;
use Sifrious\Logres\ExecutionRequest;
use Sifrious\Logres\ExecutionRequestId;
use Sifrious\Logres\StacksExecutionContext;
use Sifrious\ReferenceContract\CrossPackageReference;
use Sifrious\StacksContract\ExecutionProvenance;
use Sifrious\StacksContract\WorkspaceReference;

final class ExecutionRequestFixtures
{
    public static function accepted(): ExecutionRequest
    {
        return new ExecutionRequest(
            id: new ExecutionRequestId('request:accepted-fixture'),
            originalPrompt: " Preserve these bytes.\n",
            context: new ExecutionContext('project:atlas', 'repository:atlas-api'),
            desiredResult: 'A verified parser fix.',
            attachments: [new ExecutionAttachment('artifact:parser-log', 'parser.log')],
            constraints: new ExecutionConstraints(900, ['/workspace/atlas-api']),
            permissions: new ExecutionPermissions(true, true, false),
            authorization: self::authorization(),
            channel: DeliveryChannel::Web,
            executionIdentity: self::executionIdentity(),
        );
    }

    public static function rejected(): ExecutionRequest
    {
        return new ExecutionRequest(
            id: new ExecutionRequestId('request:rejected-fixture'),
            originalPrompt: 'Run it.',
            context: new ExecutionContext(null, null),
            desiredResult: '',
            attachments: [new ExecutionAttachment('https://example.test/private', '')],
            constraints: new ExecutionConstraints(0, ['/workspace/atlas-api']),
            permissions: new ExecutionPermissions(false, false, true),
            authorization: null,
            channel: DeliveryChannel::Web,
        );
    }

    public static function authorization(): AuthorizationContext
    {
        return new AuthorizationContext(
            new ActorContext(new CrossPackageReference('sifrious/zahir', 'account', 'fixture-user'), ActorKind::Human),
            TenantScope::forTenant('organization', new CrossPackageReference('sifrious/zahir', 'organization', 'tenant-a')),
        );
    }

    public static function executionIdentity(
        string $workspaceId = 'ws_00000000000000000000000000000001',
        string $path = '/workspace/atlas-api',
        string $revision = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
        string $repositoryId = 'repository:atlas-api',
        string $target = 'target:orbs:orb-a',
        ?string $checkoutId = null,
    ): StacksExecutionContext {
        $checkoutId ??= $workspaceId;
        $workspace = new WorkspaceReference(
            $workspaceId,
            $repositoryId,
            'github.com/sifrious/atlas-api',
            $checkoutId,
            'worktree',
            'available',
            $path,
            'main',
            $revision,
        );

        return StacksExecutionContext::capture(
            $workspace,
            new ExecutionProvenance(
                $workspaceId,
                $repositoryId,
                'github.com/sifrious/atlas-api',
                $checkoutId,
                'worktree',
                $path,
                $revision,
                'main',
                'git@github.com:sifrious/atlas-api.git',
                '2026-08-28T05:39:00+00:00',
            ),
            $revision,
            'git-worktree:'.$checkoutId,
            'capability-snapshot:'.str_repeat('c', 64),
            $target,
        );
    }
}
