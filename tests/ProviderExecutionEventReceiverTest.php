<?php

declare(strict_types=1);

namespace Sifrious\Logres\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sifrious\Logres\ExecutionEventType;
use Sifrious\Logres\ExecutionTimelineReadModel;
use Sifrious\Logres\ProviderExecutionEventEnvelope;
use Sifrious\Logres\ProviderExecutionEventLog;
use Sifrious\Logres\ProviderExecutionEventReceiver;
use Sifrious\Logres\ProviderExecutionEventStatus;
use Sifrious\Logres\RunStatus;
use Sifrious\Logres\Tests\Fixtures\ProviderExecutionEventFixtures;

final class ProviderExecutionEventReceiverTest extends TestCase
{
    #[Test]
    public function it_preserves_provider_envelopes_and_normalizes_the_taxonomy(): void
    {
        $log = ProviderExecutionEventLog::begin('provider-invocation:001', 'orbs', 'execution-001', 'run:invocation-001', 'task:invocation-001', 'attempt:001:1');
        $receiver = new ProviderExecutionEventReceiver();

        $receipt = $receiver->receive($log, ProviderExecutionEventEnvelope::fromArray(ProviderExecutionEventFixtures::accepted()));

        self::assertSame(ProviderExecutionEventStatus::Accepted, $receipt->status);
        self::assertSame(ExecutionEventType::TargetAccepted->value, $receipt->event?->type);
        self::assertCount(1, $receipt->log->providerEnvelopes);
        self::assertCount(1, $receipt->log->events);
    }

    #[Test]
    public function it_deduplicates_by_stable_event_identity(): void
    {
        $receiver = new ProviderExecutionEventReceiver();
        $log = ProviderExecutionEventLog::begin('provider-invocation:001', 'orbs', 'execution-001', 'run:invocation-001', 'task:invocation-001', 'attempt:001:1');
        $log = $receiver->receive($log, ProviderExecutionEventEnvelope::fromArray(ProviderExecutionEventFixtures::accepted()))->log;

        $first = $receiver->receive($log, ProviderExecutionEventEnvelope::fromArray(ProviderExecutionEventFixtures::duplicate()));
        $second = $receiver->receive($first->log, ProviderExecutionEventEnvelope::fromArray(ProviderExecutionEventFixtures::duplicate()));

        self::assertSame(ProviderExecutionEventStatus::Accepted, $first->status);
        self::assertSame(ProviderExecutionEventStatus::Duplicate, $second->status);
        self::assertCount(2, $second->log->events);
    }

    #[Test]
    public function it_tracks_reordered_and_late_arrivals_without_losing_events(): void
    {
        $receiver = new ProviderExecutionEventReceiver();
        $log = ProviderExecutionEventLog::begin('provider-invocation:001', 'orbs', 'execution-001', 'run:invocation-001', 'task:invocation-001', 'attempt:001:1');
        $reordered = $receiver->receive($log, ProviderExecutionEventEnvelope::fromArray(ProviderExecutionEventFixtures::reorderedEarly()));
        $ordered = $receiver->receive($reordered->log, ProviderExecutionEventEnvelope::fromArray(ProviderExecutionEventFixtures::reorderedLate()));
        $terminal = $receiver->receive($ordered->log, ProviderExecutionEventEnvelope::fromArray(ProviderExecutionEventFixtures::completed('evt-terminal', 3)));
        $late = $receiver->receive($terminal->log, ProviderExecutionEventEnvelope::fromArray(ProviderExecutionEventFixtures::lateAfterTerminal()));

        self::assertSame(ProviderExecutionEventStatus::GapDetected, $reordered->status);
        self::assertSame(ProviderExecutionEventStatus::Reordered, $ordered->status);
        self::assertSame(ProviderExecutionEventStatus::Late, $late->status);
        self::assertSame(RunStatus::Succeeded, $late->log->projectedRunStatus());
    }

    #[Test]
    public function it_marks_unknown_provider_types_and_preserves_original_payloads(): void
    {
        $receiver = new ProviderExecutionEventReceiver();
        $log = ProviderExecutionEventLog::begin('provider-invocation:001', 'orbs', 'execution-001', 'run:invocation-001', 'task:invocation-001', 'attempt:001:1');
        $log = $receiver->receive($log, ProviderExecutionEventEnvelope::fromArray(ProviderExecutionEventFixtures::accepted()))->log;
        $unknown = $receiver->receive($log, ProviderExecutionEventEnvelope::fromArray(ProviderExecutionEventFixtures::unknownType()));

        self::assertSame(ProviderExecutionEventStatus::UnknownType, $unknown->status);
        self::assertSame(ExecutionEventType::Warning->value, $unknown->event?->type);
        self::assertSame('provider.unknown.event', $unknown->event?->payload['provider_event_type'] ?? null);
    }

    #[Test]
    public function it_rejects_forged_association_and_leaves_the_log_unchanged(): void
    {
        $receiver = new ProviderExecutionEventReceiver();
        $log = ProviderExecutionEventLog::begin('provider-invocation:001', 'orbs', 'execution-001', 'run:invocation-001', 'task:invocation-001', 'attempt:001:1');
        $accepted = $receiver->receive($log, ProviderExecutionEventEnvelope::fromArray(ProviderExecutionEventFixtures::accepted()));
        $forged = $receiver->receive($accepted->log, ProviderExecutionEventEnvelope::fromArray(ProviderExecutionEventFixtures::forgedRun()));

        self::assertSame(ProviderExecutionEventStatus::Forged, $forged->status);
        self::assertCount(1, $forged->log->events);
        self::assertCount(1, $forged->log->providerEnvelopes);
    }

    #[Test]
    public function it_persists_missing_sequence_ranges_for_gap_semantics(): void
    {
        $receiver = new ProviderExecutionEventReceiver();
        $log = ProviderExecutionEventLog::begin('provider-invocation:001', 'orbs', 'execution-001', 'run:invocation-001', 'task:invocation-001', 'attempt:001:1');

        foreach (ProviderExecutionEventFixtures::missingSequenceGap() as $fixture) {
            $log = $receiver->receive($log, ProviderExecutionEventEnvelope::fromArray($fixture))->log;
        }

        self::assertSame([[2, 2]], $log->missingSequenceRanges);
    }

    #[Test]
    public function it_exposes_presentation_neutral_timeline_and_typed_references(): void
    {
        $receiver = new ProviderExecutionEventReceiver();
        $log = ProviderExecutionEventLog::begin('provider-invocation:001', 'orbs', 'execution-001', 'run:invocation-001', 'task:invocation-001', 'attempt:001:1');
        $log = $receiver->receive($log, ProviderExecutionEventEnvelope::fromArray(ProviderExecutionEventFixtures::accepted()))->log;
        $tool = ProviderExecutionEventEnvelope::fromArray([
            'event_id' => 'evt-tool-2',
            'provider' => 'orbs',
            'provider_execution_id' => 'execution-001',
            'invocation_id' => 'provider-invocation:001',
            'run_id' => 'run:invocation-001',
            'task_id' => 'task:invocation-001',
            'attempt_id' => 'attempt:001:1',
            'sequence' => 2,
            'occurred_at' => '2026-09-01T12:00:02Z',
            'event_type' => 'tool.invoked',
            'payload' => [
                'tool_invocation_id' => 'tool:abc',
                'tool_name' => 'phpunit',
            ],
        ]);
        $log = $receiver->receive($log, $tool)->log;
        $timeline = ExecutionTimelineReadModel::fromProviderLog($log);

        self::assertSame('running', $timeline->projectedRunStatus);
        self::assertSame('tool_invocation', $timeline->items[1]['payload']['reference']['kind'] ?? null);
        self::assertSame([1, 2], array_map(static fn (array $item): int => $item['sequence'], $timeline->items));
    }
}
