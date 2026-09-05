<?php

declare(strict_types=1);

namespace Sifrious\Logres\Tests;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sifrious\Logres\AttemptId;
use Sifrious\Logres\AttemptStatus;
use Sifrious\Logres\ExecutionNodeRef;
use Sifrious\Logres\ExecutionState;
use Sifrious\Logres\ExecutionStateDetails;
use Sifrious\Logres\ExecutionStateReadModel;
use Sifrious\Logres\ExecutionStateRejected;
use Sifrious\Logres\ExecutionStateRejectionReason;
use Sifrious\Logres\ExecutionStateService;
use Sifrious\Logres\LeaseId;
use Sifrious\Logres\LeaseStatus;
use Sifrious\Logres\LeaseToken;
use Sifrious\Logres\RunId;
use Sifrious\Logres\RunStatus;
use Sifrious\Logres\Tests\Fixtures\InMemoryExecutionStateStore;
use Sifrious\Logres\Tests\Fixtures\RunIdentityFixtures;

final class ExecutionStateTest extends TestCase
{
    #[Test]
    public function it_acquires_replays_and_allows_only_one_contender(): void
    {
        [$store, $service, $state] = $this->storedReadyState();
        $lease = $service->acquireLease($state->runId, new AttemptId('attempt:1'), new LeaseId('lease:1'), new ExecutionNodeRef('node:a'), new LeaseToken('secret:a'), 'acquire:1', $this->at(1), 60);
        $replay = $service->acquireLease($state->runId, new AttemptId('attempt:1'), new LeaseId('lease:1'), new ExecutionNodeRef('node:a'), new LeaseToken('secret:a'), 'acquire:1', $this->at(2), 60);

        self::assertSame('lease:1', $lease->id->value);
        self::assertSame($lease, $replay);
        self::assertCount(1, $store->find($state->runId)->currentAttempt()->leases);

        try {
            $service->acquireLease($state->runId, new AttemptId('attempt:1'), new LeaseId('lease:2'), new ExecutionNodeRef('node:b'), new LeaseToken('secret:b'), 'acquire:2', $this->at(3), 60);
            self::fail('A second contender unexpectedly acquired a Lease.');
        } catch (ExecutionStateRejected $rejected) {
            self::assertSame(ExecutionStateRejectionReason::AlreadyLeased, $rejected->reason);
        }
    }

    #[Test]
    public function it_renews_releases_and_replays_mutations(): void
    {
        $state = $this->readyState()->acquireLease(new AttemptId('attempt:1'), new LeaseId('lease:1'), new ExecutionNodeRef('node:a'), new LeaseToken('secret'), 'acquire:1', $this->at(1), 60);
        $renewed = $state->renewLease(new AttemptId('attempt:1'), new ExecutionNodeRef('node:a'), new LeaseToken('secret'), 'renew:1', $this->at(30), 90);
        self::assertEquals($this->at(120), $renewed->currentAttempt()->activeLease()->expiresAt);
        self::assertEquals($renewed, $renewed->renewLease(new AttemptId('attempt:1'), new ExecutionNodeRef('node:a'), new LeaseToken('secret'), 'renew:1', $this->at(31), 90));
        $this->assertRejected(ExecutionStateRejectionReason::ForeignLease, fn () => $renewed->renewLease(new AttemptId('attempt:1'), new ExecutionNodeRef('node:a'), new LeaseToken('foreign'), 'renew:1', $this->at(31), 90));

        $released = $renewed->releaseLease(new AttemptId('attempt:1'), new ExecutionNodeRef('node:a'), new LeaseToken('secret'), 'release:1', $this->at(40));
        self::assertSame(LeaseStatus::Released, $released->currentAttempt()->leases[0]->status);
        self::assertEquals($released, $released->releaseLease(new AttemptId('attempt:1'), new ExecutionNodeRef('node:a'), new LeaseToken('secret'), 'release:1', $this->at(41)));
        $this->assertRejected(ExecutionStateRejectionReason::ForeignLease, fn () => $released->releaseLease(new AttemptId('attempt:1'), new ExecutionNodeRef('node:a'), new LeaseToken('foreign'), 'release:1', $this->at(41)));
    }

    #[Test]
    public function it_rejects_foreign_and_expired_renewals_with_machine_reasons(): void
    {
        $state = $this->readyState()->acquireLease(new AttemptId('attempt:1'), new LeaseId('lease:1'), new ExecutionNodeRef('node:a'), new LeaseToken('secret'), 'acquire:1', $this->at(1), 60);
        $this->assertRejected(ExecutionStateRejectionReason::ForeignLease, fn () => $state->renewLease(new AttemptId('attempt:1'), new ExecutionNodeRef('node:a'), new LeaseToken('foreign'), 'renew:x', $this->at(2), 60));
        $this->assertRejected(ExecutionStateRejectionReason::NotLeaseHolder, fn () => $state->renewLease(new AttemptId('attempt:1'), new ExecutionNodeRef('node:b'), new LeaseToken('secret'), 'renew:y', $this->at(2), 60));
        $this->assertRejected(ExecutionStateRejectionReason::LeaseExpired, fn () => $state->renewLease(new AttemptId('attempt:1'), new ExecutionNodeRef('node:a'), new LeaseToken('secret'), 'renew:z', $this->at(61), 60));
    }

    #[Test]
    public function expiry_supports_same_attempt_reclaim_or_explicit_next_attempt_lineage(): void
    {
        $leased = $this->readyState()->acquireLease(new AttemptId('attempt:1'), new LeaseId('lease:1'), new ExecutionNodeRef('node:a'), new LeaseToken('secret:a'), 'acquire:1', $this->at(1), 30);
        $expired = $leased->expireLease(new AttemptId('attempt:1'), $this->at(31));
        self::assertSame(AttemptStatus::Expired, $expired->currentAttempt()->status);
        self::assertSame(LeaseStatus::Expired, $expired->currentAttempt()->leases[0]->status);

        $reclaimed = $expired->acquireLease(new AttemptId('attempt:1'), new LeaseId('lease:2'), new ExecutionNodeRef('node:b'), new LeaseToken('secret:b'), 'acquire:2', $this->at(32), 30);
        self::assertCount(2, $reclaimed->currentAttempt()->leases);

        $next = $expired->nextAttemptAfterExpiry(new AttemptId('attempt:1'), new AttemptId('attempt:2'), $this->at(32));
        self::assertSame(2, $next->currentAttempt()->number);
        self::assertSame('attempt:1', $next->currentAttempt()->previousAttemptId->value);
    }

    #[Test]
    public function a_terminal_run_is_idempotent_but_cannot_reopen_or_lease(): void
    {
        $running = $this->readyState()
            ->acquireLease(new AttemptId('attempt:1'), new LeaseId('lease:1'), new ExecutionNodeRef('node:a'), new LeaseToken('secret'), 'acquire:1', $this->at(1), 60)
            ->start(new AttemptId('attempt:1'), new LeaseToken('secret'), $this->at(2));
        $finished = $running->finish(new AttemptId('attempt:1'), new LeaseToken('secret'), RunStatus::Succeeded, $this->at(3), resultReference: 'result:1');
        self::assertSame(RunStatus::Succeeded, $finished->status);
        self::assertEquals($finished, $finished->finish(new AttemptId('attempt:1'), new LeaseToken('secret'), RunStatus::Succeeded, $this->at(3), resultReference: 'result:1'));
        $this->assertRejected(ExecutionStateRejectionReason::AlreadyTerminal, fn () => $finished->scheduleAttempt(new AttemptId('attempt:2'), $this->at(4)));
    }

    #[Test]
    public function its_read_model_exposes_current_state_lease_expiry_and_lineage(): void
    {
        $state = $this->readyState()->acquireLease(new AttemptId('attempt:1'), new LeaseId('lease:1'), new ExecutionNodeRef('node:a'), new LeaseToken('never-exported'), 'acquire:1', $this->at(1), 60);
        $read = ExecutionStateReadModel::fromState($state);
        self::assertSame('preparing', $read->status);
        self::assertSame('node:a', $read->currentAttempt['leases'][0]['holder']);
        self::assertSame('2026-08-29T12:01:01+00:00', $read->currentAttempt['leases'][0]['expires_at']);
        self::assertArrayNotHasKey('token', $read->currentAttempt['leases'][0]);
    }

    #[Test]
    public function it_preserves_the_landing_agent_task_fields_in_package_owned_current_state(): void
    {
        $details = new ExecutionStateDetails(
            title: 'Implement state machine',
            prompt: 'Implement lifecycle rules',
            createdByUserId: 'user:creator',
            workspaceId: 'workspace:abc',
            parentTaskId: 'plan:456',
            baseBranch: 'main',
            branchName: 'codex/mme-1212',
            worktreePath: '/evidence/worktree',
            sqlitePath: '/evidence/run.sqlite',
            runtimeInvocationId: 'runtime:789',
            targetReference: 'target:mac:1',
        );
        $state = ExecutionState::create(new RunId('run:details'), $this->at(0), RunIdentityFixtures::executionIdentity(), $details)
            ->recordApproval('user:approver', $this->at(1))
            ->recordExecutionResult(3, 'https://github.com/sifrious/dep-logres/pull/3', ['files' => 20], '/evidence/output.log', $this->at(2));
        $read = ExecutionStateReadModel::fromState($state);

        self::assertSame([
            'repo_id', 'parent_task_id', 'title', 'prompt', 'base_branch', 'branch_name',
            'worktree_path', 'sqlite_path', 'pr_number', 'pr_url', 'diff_stats',
            'output_log_path', 'error_message', 'created_by_user_id', 'approved_by_user_id',
            'approved_at', 'runtime_invocation_id', 'target_reference', 'updated_at',
        ], array_keys($read->details));
        self::assertSame('workspace:abc', $read->details['repo_id']);
        self::assertSame('user:approver', $read->details['approved_by_user_id']);
        self::assertSame(3, $read->details['pr_number']);
    }

    private function readyState(): ExecutionState
    {
        return ExecutionState::create(new RunId('run:1'), $this->at(0), RunIdentityFixtures::executionIdentity())->scheduleAttempt(new AttemptId('attempt:1'), $this->at(0));
    }

    private function storedReadyState(): array
    {
        $store = new InMemoryExecutionStateStore();
        $state = $this->readyState();
        $store->create($state);
        return [$store, new ExecutionStateService($store), $state];
    }

    private function at(int $seconds): DateTimeImmutable
    {
        return (new DateTimeImmutable('2026-08-29T12:00:00Z'))->modify("+{$seconds} seconds");
    }

    private function assertRejected(ExecutionStateRejectionReason $reason, callable $operation): void
    {
        try {
            $operation();
            self::fail('Expected an execution-state rejection.');
        } catch (ExecutionStateRejected $rejected) {
            self::assertSame($reason, $rejected->reason);
        }
    }
}
