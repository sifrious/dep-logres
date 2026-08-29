<?php

declare(strict_types=1);

namespace Sifrious\Logres\Tests;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sifrious\Logres\AttemptId;
use Sifrious\Logres\CancellationAuthorization;
use Sifrious\Logres\CancellationKind;
use Sifrious\Logres\ExecutionNodeRef;
use Sifrious\Logres\ExecutionState;
use Sifrious\Logres\ExecutionStateReadModel;
use Sifrious\Logres\ExecutionStateRejected;
use Sifrious\Logres\ExecutionStateRejectionReason;
use Sifrious\Logres\FailureClassification;
use Sifrious\Logres\LeaseId;
use Sifrious\Logres\LeaseToken;
use Sifrious\Logres\PostflightReport;
use Sifrious\Logres\PostflightResultAssembler;
use Sifrious\Logres\RetryPolicy;
use Sifrious\Logres\RunId;
use Sifrious\Logres\RunResult;
use Sifrious\Logres\RunStatus;
use Sifrious\Logres\Tests\Fixtures\InMemoryExecutionStateStore;
use Sifrious\Logres\VerificationStatus;

final class RemoteExecutionLifecycleConformanceTest extends TestCase
{
    #[Test]
    public function retry_recovery_reaches_one_terminal_run_and_rejects_stale_callbacks(): void
    {
        $first = $this->ready('attempt:1', 0)
            ->acquireLease(new AttemptId('attempt:1'), new LeaseId('lease:1'), new ExecutionNodeRef('node:1'), new LeaseToken('token:1'), 'acquire:1', $this->at(1), 60)
            ->start(new AttemptId('attempt:1'), new LeaseToken('token:1'), $this->at(2));
        $recovering = $first->observeFailure(new AttemptId('attempt:1'), new LeaseToken('token:1'), FailureClassification::Transient, 'failure:1', 'network', $this->at(3), new RetryPolicy(2));
        $second = $recovering->scheduleRetry(new AttemptId('attempt:2'), $this->at(4))
            ->acquireLease(new AttemptId('attempt:2'), new LeaseId('lease:2'), new ExecutionNodeRef('node:2'), new LeaseToken('token:2'), 'acquire:2', $this->at(5), 60)
            ->start(new AttemptId('attempt:2'), new LeaseToken('token:2'), $this->at(6))
            ->finish(new AttemptId('attempt:2'), new LeaseToken('token:2'), RunStatus::Succeeded, $this->at(7), resultReference: 'result:verified');

        self::assertSame(RunStatus::Succeeded, $second->status);
        self::assertCount(2, $second->attempts);
        self::assertSame('attempt:1', $second->attempts[1]->previousAttemptId->value);
        $this->assertRejected(ExecutionStateRejectionReason::AlreadyTerminal, fn () => $second->finish(new AttemptId('attempt:1'), new LeaseToken('token:1'), RunStatus::Succeeded, $this->at(8), resultReference: 'result:verified'));
        $this->assertRejected(ExecutionStateRejectionReason::AlreadyTerminal, fn () => $second->scheduleRetry(new AttemptId('attempt:3'), $this->at(8)));
    }

    #[Test]
    public function lost_ack_and_restart_converge_without_a_second_attempt_or_lease(): void
    {
        $uncertain = $this->ready('attempt:1', 0)
            ->acquireLease(new AttemptId('attempt:1'), new LeaseId('lease:1'), new ExecutionNodeRef('node:1'), new LeaseToken('token:1'), 'acquire:1', $this->at(1), 60)
            ->start(new AttemptId('attempt:1'), new LeaseToken('token:1'), $this->at(2))
            ->observeFailure(new AttemptId('attempt:1'), new LeaseToken('token:1'), FailureClassification::AcknowledgementUncertain, 'ack:lost', 'accepted remotely; acknowledgement lost', $this->at(3), new RetryPolicy(2));
        $store = new InMemoryExecutionStateStore();
        $store->create($uncertain);
        $reloaded = $store->find(new RunId('run:conformance'));
        $running = $reloaded->confirmAcknowledgement(new AttemptId('attempt:1'), new LeaseToken('token:1'), $this->at(4));

        self::assertCount(1, $running->attempts);
        self::assertCount(1, $running->currentAttempt()->leases);
        self::assertSame('running', ExecutionStateReadModel::fromState($running)->status);
    }

    #[Test]
    public function cancellation_and_verification_disposition_remain_independent_and_evidence_bearing(): void
    {
        $running = $this->ready('attempt:1', 0)
            ->acquireLease(new AttemptId('attempt:1'), new LeaseId('lease:1'), new ExecutionNodeRef('node:1'), new LeaseToken('token:1'), 'acquire:1', $this->at(1), 60)
            ->start(new AttemptId('attempt:1'), new LeaseToken('token:1'), $this->at(2));
        $cancelled = $running
            ->requestCancellation('cancel:1', CancellationKind::Manual, 'user:1', 'operator', CancellationAuthorization::allow(), $this->at(3))
            ->confirmCancellation(new AttemptId('attempt:1'), new LeaseToken('token:1'), 'cancel:1', $this->at(4), 'partial:git-and-logs');
        $result = (new PostflightResultAssembler)->assemble(
            RunResult::cancelled(reason: 'operator'),
            new PostflightReport([], 'Partial evidence retained at partial:git-and-logs.', $this->at(5)->format(DATE_ATOM), VerificationStatus::Incomplete),
        );

        self::assertSame(RunStatus::Cancelled, $cancelled->status);
        self::assertSame('partial:git-and-logs', $cancelled->terminalResultReference);
        self::assertSame(RunStatus::Cancelled, $result->status);
        self::assertFalse($result->isVerifiedSuccess());
    }

    private function ready(string $attemptId, int $seconds): ExecutionState
    {
        return ExecutionState::create(new RunId('run:conformance'), $this->at($seconds))->scheduleAttempt(new AttemptId($attemptId), $this->at($seconds));
    }

    private function at(int $seconds): DateTimeImmutable
    {
        return (new DateTimeImmutable('2026-08-29T12:00:00Z'))->modify("+{$seconds} seconds");
    }

    private function assertRejected(ExecutionStateRejectionReason $reason, callable $operation): void
    {
        try {
            $operation();
            self::fail('Expected conformance rejection.');
        } catch (ExecutionStateRejected $rejected) {
            self::assertSame($reason, $rejected->reason);
        }
    }
}
