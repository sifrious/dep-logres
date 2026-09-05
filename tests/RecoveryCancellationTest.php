<?php

declare(strict_types=1);

namespace Sifrious\Logres\Tests;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sifrious\Logres\AttemptId;
use Sifrious\Logres\AttemptStatus;
use Sifrious\Logres\CancellationAuthorization;
use Sifrious\Logres\CancellationKind;
use Sifrious\Logres\CancellationStatus;
use Sifrious\Logres\ExecutionNodeRef;
use Sifrious\Logres\ExecutionState;
use Sifrious\Logres\ExecutionStateReadModel;
use Sifrious\Logres\ExecutionStateRejected;
use Sifrious\Logres\ExecutionStateRejectionReason;
use Sifrious\Logres\FailureClassification;
use Sifrious\Logres\LeaseId;
use Sifrious\Logres\LeaseToken;
use Sifrious\Logres\RecoveryAction;
use Sifrious\Logres\RetryPolicy;
use Sifrious\Logres\RunId;
use Sifrious\Logres\RunStatus;
use Sifrious\Logres\Tests\Fixtures\InMemoryExecutionStateStore;
use Sifrious\Logres\Tests\Fixtures\RunIdentityFixtures;

final class RecoveryCancellationTest extends TestCase
{
    #[Test]
    public function transient_failure_replays_and_creates_a_linked_retry_attempt(): void
    {
        $running = $this->running();
        $failed = $running->observeFailure(new AttemptId('attempt:1'), new LeaseToken('secret:1'), FailureClassification::Transient, 'failure:1', 'provider unavailable', $this->at(3), new RetryPolicy(3));

        self::assertSame(RunStatus::Reconciling, $failed->status);
        self::assertSame(RecoveryAction::Retry, $failed->recovery->action);
        self::assertNull($failed->activeAttemptId);
        self::assertEquals($failed, $failed->observeFailure(new AttemptId('attempt:1'), new LeaseToken('secret:1'), FailureClassification::Transient, 'failure:1', 'provider unavailable', $this->at(4), new RetryPolicy(3)));
        $this->assertRejected(ExecutionStateRejectionReason::ForeignLease, fn () => $failed->observeFailure(new AttemptId('attempt:1'), new LeaseToken('foreign'), FailureClassification::Transient, 'failure:1', 'provider unavailable', $this->at(4), new RetryPolicy(3)));

        $retry = $failed->scheduleRetry(new AttemptId('attempt:2'), $this->at(5));
        self::assertSame(RunStatus::Preparing, $retry->status);
        self::assertSame(2, $retry->currentAttempt()->number);
        self::assertSame('attempt:1', $retry->currentAttempt()->previousAttemptId->value);
        self::assertEquals($retry, $retry->scheduleRetry(new AttemptId('attempt:2'), $this->at(6)));
    }

    #[Test]
    public function permanent_or_exhausted_failure_is_terminal_and_cannot_reopen(): void
    {
        $failed = $this->running()->observeFailure(new AttemptId('attempt:1'), new LeaseToken('secret:1'), FailureClassification::Permanent, 'failure:1', 'invalid request', $this->at(3), new RetryPolicy(3));
        self::assertSame(RunStatus::Failed, $failed->status);
        self::assertSame(RecoveryAction::Fail, $failed->recovery->action);
        $this->assertRejected(ExecutionStateRejectionReason::AlreadyTerminal, fn () => $failed->scheduleRetry(new AttemptId('attempt:2'), $this->at(4)));

        $exhausted = $this->running()->observeFailure(new AttemptId('attempt:1'), new LeaseToken('secret:1'), FailureClassification::Transient, 'failure:2', 'still unavailable', $this->at(3), new RetryPolicy(1));
        self::assertSame(RecoveryAction::Fail, $exhausted->recovery->action);
    }

    #[Test]
    public function lost_acknowledgement_reconciles_the_same_attempt_without_duplicate_execution(): void
    {
        $uncertain = $this->running()->observeFailure(new AttemptId('attempt:1'), new LeaseToken('secret:1'), FailureClassification::AcknowledgementUncertain, 'ack:1', 'provider accepted but response was lost', $this->at(3), new RetryPolicy(3));
        self::assertSame(RunStatus::Reconciling, $uncertain->status);
        self::assertSame(AttemptStatus::ReconciliationRequired, $uncertain->currentAttempt()->status);
        self::assertSame(RecoveryAction::Reconcile, $uncertain->recovery->action);
        $this->assertRejected(ExecutionStateRejectionReason::AlreadyLeased, fn () => $uncertain->acquireLease(new AttemptId('attempt:1'), new LeaseId('lease:2'), new ExecutionNodeRef('node:2'), new LeaseToken('secret:2'), 'acquire:2', $this->at(4), 60));

        $recovered = $uncertain->confirmAcknowledgement(new AttemptId('attempt:1'), new LeaseToken('secret:1'), $this->at(4));
        self::assertSame(RunStatus::Running, $recovered->status);
        self::assertSame('attempt:1', $recovered->currentAttempt()->id->value);
        self::assertCount(1, $recovered->attempts);
        self::assertEquals($recovered, $recovered->confirmAcknowledgement(new AttemptId('attempt:1'), new LeaseToken('secret:1'), $this->at(5)));
    }

    #[Test]
    public function recovery_state_survives_the_persistence_boundary(): void
    {
        $state = $this->running()->observeFailure(new AttemptId('attempt:1'), new LeaseToken('secret:1'), FailureClassification::Transient, 'failure:1', 'network', $this->at(3), new RetryPolicy(2));
        $store = new InMemoryExecutionStateStore();
        $store->create($state);
        $reloaded = $store->find($state->runId);
        $read = ExecutionStateReadModel::fromState($reloaded);

        self::assertSame('reconciling', $read->status);
        self::assertSame('retry', $read->recovery['action']);
        self::assertSame('attempt:1', $read->recovery['attempt_id']);
    }

    #[Test]
    public function unauthorized_cancellation_is_rejected_and_predispatch_cancellation_is_terminal(): void
    {
        $pending = ExecutionState::create(new RunId('run:cancel'), $this->at(0), RunIdentityFixtures::executionIdentity());
        $this->assertRejected(ExecutionStateRejectionReason::CancellationUnauthorized, fn () => $pending->requestCancellation('cancel:denied', CancellationKind::Manual, 'user:1', 'stop', CancellationAuthorization::deny('actor_not_allowed'), $this->at(1)));

        $cancelled = $pending->requestCancellation('cancel:1', CancellationKind::Manual, 'user:1', 'operator stopped work', CancellationAuthorization::allow(), $this->at(1));
        self::assertSame(RunStatus::Cancelled, $cancelled->status);
        self::assertSame(CancellationStatus::Confirmed, $cancelled->cancellation->status);
        self::assertNull($cancelled->activeAttemptId);
        self::assertEquals($cancelled, $cancelled->requestCancellation('cancel:1', CancellationKind::Manual, 'user:1', 'operator stopped work', CancellationAuthorization::allow(), $this->at(2)));
    }

    #[Test]
    public function active_cancellation_is_durable_idempotent_and_preserves_partial_evidence(): void
    {
        $requested = $this->running()->requestCancellation('cancel:1', CancellationKind::Manual, 'user:1', 'operator stopped work', CancellationAuthorization::allow(), $this->at(3));
        self::assertSame(CancellationStatus::Requested, $requested->cancellation->status);
        self::assertSame(RunStatus::Running, $requested->status);
        $this->assertRejected(ExecutionStateRejectionReason::CancellationPending, fn () => $requested->renewLease(new AttemptId('attempt:1'), new ExecutionNodeRef('node:1'), new LeaseToken('secret:1'), 'renew:1', $this->at(4), 60));

        $cancelled = $requested->confirmCancellation(new AttemptId('attempt:1'), new LeaseToken('secret:1'), 'cancel:1', $this->at(5), 'partial:logs:1');
        self::assertSame(RunStatus::Cancelled, $cancelled->status);
        self::assertSame('partial:logs:1', $cancelled->terminalResultReference);
        self::assertSame(AttemptStatus::Cancelled, $cancelled->attempts[0]->status);
        self::assertEquals($cancelled, $cancelled->confirmCancellation(new AttemptId('attempt:1'), new LeaseToken('secret:1'), 'cancel:1', $this->at(6), 'partial:logs:1'));
        $this->assertRejected(ExecutionStateRejectionReason::ForeignLease, fn () => $cancelled->confirmCancellation(new AttemptId('attempt:1'), new LeaseToken('foreign'), 'cancel:1', $this->at(6), 'partial:logs:1'));
    }

    #[Test]
    public function timeout_remains_distinct_from_manual_cancellation(): void
    {
        $requested = $this->running()->requestCancellation('timeout:1', CancellationKind::Timeout, 'policy:max-duration', 'maximum duration exceeded', CancellationAuthorization::allow(), $this->at(3));
        $timedOut = $requested->confirmCancellation(new AttemptId('attempt:1'), new LeaseToken('secret:1'), 'timeout:1', $this->at(5));
        $read = ExecutionStateReadModel::fromState($timedOut);

        self::assertSame(RunStatus::TimedOut, $timedOut->status);
        self::assertSame(AttemptStatus::TimedOut, $timedOut->attempts[0]->status);
        self::assertSame('timeout', $read->cancellation['kind']);
    }

    #[Test]
    public function cancellation_during_retry_reconciliation_prevents_the_next_attempt(): void
    {
        $reconciling = $this->running()->observeFailure(new AttemptId('attempt:1'), new LeaseToken('secret:1'), FailureClassification::Transient, 'failure:1', 'network', $this->at(3), new RetryPolicy(3));
        $cancelled = $reconciling->requestCancellation('cancel:recovery', CancellationKind::Manual, 'user:1', 'stop retrying', CancellationAuthorization::allow(), $this->at(4));

        self::assertSame(RunStatus::Cancelled, $cancelled->status);
        $this->assertRejected(ExecutionStateRejectionReason::AlreadyTerminal, fn () => $cancelled->scheduleRetry(new AttemptId('attempt:2'), $this->at(5)));
    }

    private function running(): ExecutionState
    {
        return ExecutionState::create(new RunId('run:1'), $this->at(0), RunIdentityFixtures::executionIdentity())
            ->scheduleAttempt(new AttemptId('attempt:1'), $this->at(0))
            ->acquireLease(new AttemptId('attempt:1'), new LeaseId('lease:1'), new ExecutionNodeRef('node:1'), new LeaseToken('secret:1'), 'acquire:1', $this->at(1), 60)
            ->start(new AttemptId('attempt:1'), new LeaseToken('secret:1'), $this->at(2));
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
