<?php

declare(strict_types=1);

namespace Sifrious\Logres\Tests;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sifrious\Logres\DispatchAuthorizationPolicy;
use Sifrious\Logres\ExecutionTargetAuthorization;
use Sifrious\Logres\ExecutionTargetId;
use Sifrious\Logres\ExecutionTargetSelector;
use Sifrious\Logres\RepositoryIdentity;
use Sifrious\Logres\TargetAvailability;
use Sifrious\Logres\WorkspacePath;
use Sifrious\Logres\Tests\Fixtures\ExecutionTargetFixtures;
use Sifrious\Logres\Tests\Fixtures\RunIdentityFixtures;

final class DispatchAuthorizationPolicyTest extends TestCase
{
    #[Test]
    public function complete_matching_authority_freezes_dispatch_context_on_the_run(): void
    {
        $run = RunIdentityFixtures::unauthorizedRun();
        $decision = $this->authorize($run);
        $authorized = $run->authorized($decision);
        $awaiting = $authorized->awaitingAcknowledgement(RunIdentityFixtures::DISPATCHED_AT);

        self::assertTrue($decision->allowed);
        self::assertSame('/workspace/atlas', $awaiting->dispatchAuthorization?->workspacePath->value);
        self::assertSame('production', $awaiting->dispatchAuthorization?->environment);
        self::assertSame(['filesystem:read'], $awaiting->dispatchAuthorization?->permissions);
    }

    #[Test]
    public function run_cannot_enter_dispatch_without_an_allowed_snapshot(): void
    {
        $this->expectException(InvalidArgumentException::class);
        RunIdentityFixtures::unauthorizedRun()->awaitingAcknowledgement(RunIdentityFixtures::DISPATCHED_AT);
    }

    #[Test]
    public function frozen_dispatch_authorization_cannot_be_replaced(): void
    {
        $run = RunIdentityFixtures::unauthorizedRun();
        $authorized = $run->authorized($this->authorize($run));
        $replacement = $this->authorize($authorized);

        self::assertContains('run_already_authorized', $this->codes($replacement));

        $this->expectException(InvalidArgumentException::class);
        $authorized->authorized($this->authorize($run));
    }

    #[Test]
    public function wrong_or_ambiguous_repository_fails_closed(): void
    {
        $run = RunIdentityFixtures::unauthorizedRun();
        $wrong = $this->authorize($run, repositories: [new RepositoryIdentity('repository:other')]);
        $ambiguous = $this->authorize($run, repositories: [
            new RepositoryIdentity('repository:atlas'),
            new RepositoryIdentity('repository:other'),
        ]);

        self::assertContains('repository_mismatch', $this->codes($wrong));
        self::assertContains('repository_ambiguous', $this->codes($ambiguous));
    }

    #[Test]
    public function missing_workspace_and_path_escape_fail_closed(): void
    {
        $run = RunIdentityFixtures::unauthorizedRun();
        $missing = $this->authorize($run, path: null);
        $escaped = $this->authorize($run, path: WorkspacePath::fromInput('/workspace/atlas/../../etc'));

        self::assertContains('workspace_path_missing', $this->codes($missing));
        self::assertContains('workspace_path_escape', $this->codes($escaped));
    }

    #[Test]
    public function stale_grant_and_stale_target_are_rejected(): void
    {
        $run = RunIdentityFixtures::unauthorizedRun();
        $staleGrant = (new DispatchAuthorizationPolicy)->authorize(
            $run,
            RunIdentityFixtures::grant(expiresAt: '2026-08-28T05:30:00Z'),
            WorkspacePath::fromInput('/workspace/atlas'),
            [new RepositoryIdentity('repository:atlas')],
            'production',
            RunIdentityFixtures::CREATED_AT,
        );
        $staleTarget = (new DispatchAuthorizationPolicy(30))->authorize(
            $run,
            RunIdentityFixtures::grant(),
            WorkspacePath::fromInput('/workspace/atlas'),
            [new RepositoryIdentity('repository:atlas')],
            'production',
            RunIdentityFixtures::CREATED_AT,
        );

        self::assertContains('grant_stale', $this->codes($staleGrant));
        self::assertContains('target_snapshot_stale', $this->codes($staleTarget));
    }

    #[Test]
    public function target_capability_does_not_replace_an_explicit_permission_grant(): void
    {
        $run = RunIdentityFixtures::unauthorizedRun();
        $decision = (new DispatchAuthorizationPolicy)->authorize(
            $run,
            RunIdentityFixtures::grant(permissions: ['git']),
            WorkspacePath::fromInput('/workspace/atlas'),
            [new RepositoryIdentity('repository:atlas')],
            'production',
            RunIdentityFixtures::CREATED_AT,
        );

        self::assertContains('permissions_missing', $this->codes($decision));
    }

    #[Test]
    public function environment_runtime_and_manual_target_override_must_match_the_grant(): void
    {
        $run = RunIdentityFixtures::unauthorizedRun();
        $environment = (new DispatchAuthorizationPolicy)->authorize(
            $run,
            RunIdentityFixtures::grant(environment: 'staging'),
            WorkspacePath::fromInput('/workspace/atlas'),
            [new RepositoryIdentity('repository:atlas')],
            'production',
            RunIdentityFixtures::CREATED_AT,
        );
        $runtime = (new DispatchAuthorizationPolicy)->authorize(
            $run,
            RunIdentityFixtures::grant(runtime: 'debian-12:a1.medium'),
            WorkspacePath::fromInput('/workspace/atlas'),
            [new RepositoryIdentity('repository:atlas')],
            'production',
            RunIdentityFixtures::CREATED_AT,
        );
        $override = (new DispatchAuthorizationPolicy)->authorize(
            $run,
            RunIdentityFixtures::grant(targetId: new ExecutionTargetId('target:orbs:orb-b')),
            WorkspacePath::fromInput('/workspace/atlas'),
            [new RepositoryIdentity('repository:atlas')],
            'production',
            RunIdentityFixtures::CREATED_AT,
        );

        self::assertContains('environment_mismatch', $this->codes($environment));
        self::assertContains('runtime_mismatch', $this->codes($runtime));
        self::assertContains('target_unauthorized', $this->codes($override));
    }

    #[Test]
    public function unavailable_target_never_reaches_dispatch_authorization(): void
    {
        $requirements = ExecutionTargetFixtures::requirements();
        $selection = (new ExecutionTargetSelector)->select(
            $requirements,
            [ExecutionTargetFixtures::candidate(availability: TargetAvailability::Busy)],
            new ExecutionTargetAuthorization('user:mary', ['target:orbs:orb-a'], ['workspace:personal'], ['repository:atlas']),
            RunIdentityFixtures::CREATED_AT,
        );

        self::assertFalse($selection->selectedSuccessfully());
        self::assertSame('target_unavailable', $selection->failures[0]->code);
    }

    #[Test]
    public function repository_remote_normalization_does_not_use_a_local_path(): void
    {
        self::assertSame(
            'repository:github.com/sifrious/burdgeon',
            RepositoryIdentity::fromRemote('git@github.com:Sifrious/Burdgeon.git')->value,
        );
        self::assertSame(
            RepositoryIdentity::fromRemote('https://github.com/sifrious/burdgeon.git')->value,
            RepositoryIdentity::fromRemote('ssh://git@github.com/Sifrious/Burdgeon.git')->value,
        );

        $this->expectException(InvalidArgumentException::class);
        RepositoryIdentity::fromRemote('/workspace/burdgeon');
    }

    private function authorize(
        \Sifrious\Logres\Run $run,
        ?WorkspacePath $path = new WorkspacePath('/workspace/atlas'),
        array $repositories = [new RepositoryIdentity('repository:atlas')],
    ): \Sifrious\Logres\DispatchAuthorizationDecision {
        return (new DispatchAuthorizationPolicy)->authorize(
            $run,
            RunIdentityFixtures::grant(),
            $path,
            $repositories,
            'production',
            RunIdentityFixtures::CREATED_AT,
        );
    }

    private function codes(\Sifrious\Logres\DispatchAuthorizationDecision $decision): array
    {
        return array_column($decision->failures, 'code');
    }
}
