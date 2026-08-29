<?php

declare(strict_types=1);

namespace Sifrious\Logres\Tests;

use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Sifrious\Logres\ExecutionEvent;
use Sifrious\Logres\RunExecutionRecord;
use Sifrious\Logres\RunId;
use Sifrious\Logres\RunResult;
use Sifrious\Logres\RunnerLease;
use Sifrious\Logres\RunnerLeaseStatus;
use Sifrious\Logres\ExecutionTargetId;
use Sifrious\Logres\StaleRunnerLease;

final class RunnerLifecycleTest extends TestCase
{
    public function test_duplicate_event_is_idempotent_and_conflict_is_rejected(): void
    {
        $event = new ExecutionEvent(1, 'started', new DateTimeImmutable('2026-08-28T12:00:00Z'));
        $record = (new RunExecutionRecord(new RunId('run:one')))->append($event)->append($event);
        self::assertCount(1, $record->events);

        $this->expectException(InvalidArgumentException::class);
        $record->append(new ExecutionEvent(1, 'different', new DateTimeImmutable('2026-08-28T12:00:00Z')));
    }

    public function test_result_and_cancellation_are_idempotent_and_terminal(): void
    {
        $record = (new RunExecutionRecord(new RunId('run:one')))->cancel(new DateTimeImmutable('2026-08-28T12:00:00Z'));
        $result = RunResult::cancelled(reason: 'operator');
        self::assertEquals($record->finish($result), $record->finish($result)->finish($result));

        $this->expectException(InvalidArgumentException::class);
        $record->finish(RunResult::succeeded());
    }

    public function test_duplicate_acknowledgement_is_idempotent_and_stale_lease_is_rejected(): void
    {
        $lease = new RunnerLease('lease:one', new RunId('run:one'), new ExecutionTargetId('target:mac:one'), 'runner:one', RunnerLeaseStatus::Offered, new DateTimeImmutable('2026-08-28T12:00:00Z'), new DateTimeImmutable('2026-08-28T12:01:00Z'));
        $ack = $lease->acknowledge(new DateTimeImmutable('2026-08-28T12:00:30Z'));
        self::assertSame($ack, $ack->acknowledge(new DateTimeImmutable('2026-08-28T12:00:40Z')));

        $this->expectException(StaleRunnerLease::class);
        $lease->acknowledge(new DateTimeImmutable('2026-08-28T12:01:00Z'));
    }

    public function test_only_an_expired_acknowledged_lease_can_be_recovered(): void
    {
        $lease = new RunnerLease('lease:one', new RunId('run:one'), new ExecutionTargetId('target:amp:one'), 'runner:one', RunnerLeaseStatus::Offered, new DateTimeImmutable('2026-08-28T12:00:00Z'), new DateTimeImmutable('2026-08-28T12:01:00Z'));
        $acknowledged = $lease->acknowledge(new DateTimeImmutable('2026-08-28T12:00:30Z'));
        $recovered = $acknowledged->recover(new DateTimeImmutable('2026-08-28T12:01:00Z'), 60);

        self::assertSame(RunnerLeaseStatus::Acknowledged, $recovered->status);
        self::assertEquals(new DateTimeImmutable('2026-08-28T12:01:00Z'), $recovered->leasedAt);
        self::assertEquals(new DateTimeImmutable('2026-08-28T12:02:00Z'), $recovered->expiresAt);
        self::assertEquals($acknowledged->acknowledgedAt, $recovered->acknowledgedAt);

        $this->expectException(InvalidArgumentException::class);
        $acknowledged->recover(new DateTimeImmutable('2026-08-28T12:00:59Z'), 60);
    }
}
