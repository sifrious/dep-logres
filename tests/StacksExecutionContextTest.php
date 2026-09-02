<?php

declare(strict_types=1);

namespace Sifrious\Logres\Tests;

use PHPUnit\Framework\TestCase;
use Sifrious\Logres\StacksExecutionContext;
use Sifrious\StacksContract\ExecutionProvenance;
use Sifrious\StacksContract\WorkspaceReference;

final class StacksExecutionContextTest extends TestCase
{
    public function test_published_stacks_identity_and_provenance_are_the_canonical_run_snapshot(): void
    {
        $context = $this->context();
        $snapshot = $context->toArray();

        self::assertSame('stacks.workspace-reference.v1', $snapshot['workspace_reference']['schema']);
        self::assertSame('stacks.execution-provenance.v1', $snapshot['execution_provenance']['schema']);
        self::assertSame('ws_00000000000000000000000000000001', $context->workspace->workspaceId);
        self::assertSame('/work/original', $context->provenance->executionPath);
        self::assertNotSame($context->workspace->workspaceId, $context->provenance->executionPath);
    }

    public function test_historical_snapshot_survives_every_live_workspace_divergence(): void
    {
        $context = $this->context();
        $snapshot = $context->toArray();
        $laterObservations = [
            'moved' => $this->workspace(path: '/work/moved'),
            'unavailable' => $this->workspace(availability: 'unavailable'),
            'deleted-worktree' => $this->workspace(availability: 'unavailable', path: '/work/deleted'),
            'head-advanced' => $this->workspace(head: str_repeat('b', 40)),
            'remote-changed' => $this->workspace(remoteIdentity: 'github.com/acme/replacement'),
        ];

        foreach ($laterObservations as $name => $later) {
            self::assertNotSame($snapshot['workspace_reference'], $later->toArray(), $name);
            self::assertSame($snapshot, $context->toArray(), $name.' must not rewrite the historical Run');
        }
    }

    public function test_unavailable_workspace_cannot_create_new_execution_context(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new StacksExecutionContext($this->workspace(availability: 'unavailable'), $this->provenance());
    }

    public function test_reference_and_provenance_must_describe_one_dispatch_observation(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new StacksExecutionContext($this->workspace(path: '/work/moved'), $this->provenance());
    }

    private function context(): StacksExecutionContext
    {
        return new StacksExecutionContext($this->workspace(), $this->provenance());
    }

    private function workspace(
        string $availability = 'available',
        string $path = '/work/original',
        string $head = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
        string $remoteIdentity = 'github.com/acme/example',
    ): WorkspaceReference {
        return new WorkspaceReference(
            'ws_00000000000000000000000000000001',
            'repo_0000000000000000000000000001',
            $remoteIdentity,
            'ws_00000000000000000000000000000001',
            'worktree',
            $availability,
            $path,
            'main',
            $head,
        );
    }

    private function provenance(): ExecutionProvenance
    {
        return new ExecutionProvenance(
            'ws_00000000000000000000000000000001',
            'repo_0000000000000000000000000001',
            'github.com/acme/example',
            'ws_00000000000000000000000000000001',
            'worktree',
            '/work/original',
            'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
            'main',
            'git@github.com:acme/example.git',
            '2026-09-02T03:00:00+00:00',
            ['runtime' => 'fixture'],
        );
    }
}
