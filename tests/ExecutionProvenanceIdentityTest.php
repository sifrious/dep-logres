<?php

declare(strict_types=1);

namespace Sifrious\Logres\Tests;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sifrious\Logres\ExecutionIdentityResolver;
use Sifrious\Logres\ExecutionProvenanceClassification;
use Sifrious\Logres\StacksExecutionContext;
use Sifrious\Logres\StacksWorkspaceResolver;
use Sifrious\Logres\Tests\Fixtures\RunIdentityFixtures;
use Sifrious\StacksContract\ExecutionProvenance;
use Sifrious\StacksContract\WorkspaceReference;

final class ExecutionProvenanceIdentityTest extends TestCase
{
    #[Test]
    public function two_isolated_worktrees_of_one_repository_have_distinct_canonical_identity(): void
    {
        $first = $this->identity('ws_first', '/tmp/worktree-a', 'checkout:first');
        $second = $this->identity('ws_second', '/tmp/worktree-b', 'checkout:second');

        self::assertSame($first->workspace->repositoryId, $second->workspace->repositoryId);
        self::assertNotSame($first->canonicalIdentity(), $second->canonicalIdentity());
        self::assertNotSame($first->workspaceId(), $second->workspaceId());

        $moved = $this->identity('ws_first', '/tmp/a-moved', 'checkout:first');
        self::assertSame($first->canonicalIdentity(), $moved->canonicalIdentity(), 'A path observation is never canonical identity.');
    }

    #[Test]
    public function revision_change_after_approval_is_rejected_until_explicitly_reauthorized(): void
    {
        $run = RunIdentityFixtures::run();
        $changed = $this->identity(
            'workspace:personal',
            '/workspace/atlas',
            'worktree:atlas:personal',
            str_repeat('b', 40),
            'repository:atlas',
            'target:orbs:orb-a',
        );

        $this->expectException(InvalidArgumentException::class);
        $run->revalidateForDispatch($changed);
    }

    #[Test]
    public function resolver_rejects_unknown_ambiguous_wrong_repository_and_wrong_path(): void
    {
        $path = sys_get_temp_dir().'/logres-stacks-'.bin2hex(random_bytes(4));
        mkdir($path);
        $workspace = $this->workspace('ws_one', $path, 'checkout:one');

        try {
            $unknown = $this->resolver([]);
            $ambiguous = $this->resolver([$workspace, $workspace]);
            $single = $this->resolver([$workspace]);

            foreach ([
                fn () => $unknown->resolve('missing', 'repository:shared', $path, 'main', 'worktree:one', 'cap:v1', 'target:local:one'),
                fn () => $ambiguous->resolve('ambiguous', 'repository:shared', $path, 'main', 'worktree:one', 'cap:v1', 'target:local:one'),
                fn () => $single->resolve('ws_one', 'repository:other', $path, 'main', 'worktree:one', 'cap:v1', 'target:local:one'),
                fn () => $single->resolve('ws_one', 'repository:shared', $path.'/missing', 'main', 'worktree:one', 'cap:v1', 'target:local:one'),
            ] as $operation) {
                try {
                    $operation();
                    self::fail('Invalid workspace resolution must fail closed.');
                } catch (InvalidArgumentException) {
                    self::assertTrue(true);
                }
            }
        } finally {
            rmdir($path);
        }
    }

    #[Test]
    public function migration_classifies_missing_and_partial_provenance_without_inventing_identity(): void
    {
        self::assertSame(
            ['classification' => 'legacy_missing', 'workspace_id' => null],
            ExecutionProvenanceClassification::missingRecord(),
        );

        $partial = new StacksExecutionContext(
            $this->workspace('ws_legacy', '/tmp/legacy', 'checkout:legacy'),
            $this->provenance('ws_legacy', '/tmp/legacy', 'checkout:legacy'),
        );

        self::assertSame(ExecutionProvenanceClassification::LegacyStacksV1, $partial->classification());
        self::assertFalse($partial->isDispatchable());
        self::assertNull(StacksExecutionContext::fromArray(ExecutionProvenanceClassification::missingRecord()));
    }

    private function resolver(array $matches): ExecutionIdentityResolver
    {
        return new ExecutionIdentityResolver(new class($matches) implements StacksWorkspaceResolver {
            public function __construct(private readonly array $matches) {}
            public function resolve(string $workspaceReference): array { return $this->matches; }
            public function captureExecutionProvenance(WorkspaceReference $workspace): ExecutionProvenance
            {
                return new ExecutionProvenance(
                    $workspace->workspaceId,
                    $workspace->repositoryId,
                    $workspace->remoteIdentity,
                    $workspace->checkoutId,
                    $workspace->checkoutType,
                    $workspace->currentPath,
                    $workspace->head,
                    $workspace->branch,
                    'git@github.com:sifrious/shared.git',
                    '2026-09-04T13:00:00+00:00',
                );
            }
        });
    }

    private function identity(
        string $workspaceId,
        string $path,
        string $checkoutId,
        string $revision = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
        string $repositoryId = 'repository:shared',
        string $target = 'target:local:one',
    ): StacksExecutionContext {
        $workspace = $this->workspace($workspaceId, $path, $checkoutId, $revision, $repositoryId);

        return StacksExecutionContext::capture(
            $workspace,
            $this->provenance($workspaceId, $path, $checkoutId, $revision, $repositoryId),
            'main',
            'git-worktree:'.$checkoutId,
            'capability-snapshot:v1',
            $target,
        );
    }

    private function workspace(
        string $workspaceId,
        string $path,
        string $checkoutId,
        string $revision = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
        string $repositoryId = 'repository:shared',
    ): WorkspaceReference {
        return new WorkspaceReference(
            $workspaceId,
            $repositoryId,
            'github.com/sifrious/shared',
            $checkoutId,
            'worktree',
            'available',
            $path,
            'main',
            $revision,
        );
    }

    private function provenance(
        string $workspaceId,
        string $path,
        string $checkoutId,
        string $revision = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
        string $repositoryId = 'repository:shared',
    ): ExecutionProvenance {
        return new ExecutionProvenance(
            $workspaceId,
            $repositoryId,
            'github.com/sifrious/shared',
            $checkoutId,
            'worktree',
            $path,
            $revision,
            'main',
            'git@github.com:sifrious/shared.git',
            '2026-09-04T13:00:00+00:00',
        );
    }
}
