<?php

declare(strict_types=1);

namespace Sifrious\Logres\Tests;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sifrious\Logres\ArtifactAccessClassification;
use Sifrious\Logres\ExecutionEventType;
use Sifrious\Logres\ExecutionTimelineReadModel;
use Sifrious\Logres\ProviderExecutionEventEnvelope;
use Sifrious\Logres\ProviderExecutionEventLog;
use Sifrious\Logres\ProviderExecutionEventReceiver;
use Sifrious\Logres\ProviderExecutionEventStatus;
use Sifrious\Logres\RunArtifactAttachmentStatus;
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

    #[Test]
    public function it_maps_live_provider_artifact_events_into_durable_run_attachments(): void
    {
        $receiver = new ProviderExecutionEventReceiver();
        $log = ProviderExecutionEventLog::begin('provider-invocation:001', 'orbs', 'execution-001', 'run:invocation-001', 'task:invocation-001', 'attempt:001:1');
        $log = $receiver->receive($log, ProviderExecutionEventEnvelope::fromArray(ProviderExecutionEventFixtures::accepted()))->log;

        foreach (ProviderExecutionEventFixtures::artifactManifest() as $fixture) {
            $log = $receiver->receive($log, ProviderExecutionEventEnvelope::fromArray($fixture))->log;
        }

        self::assertCount(6, $log->artifactAttachments);
        self::assertSame(['commit', 'diff', 'bounded_log', 'test_result', 'screenshot', 'external_url'], array_values(array_map(
            static fn ($attachment): string => $attachment->artifact->type,
            $log->artifactAttachments,
        )));

        $timeline = ExecutionTimelineReadModel::fromProviderLog($log);
        self::assertCount(6, $timeline->artifacts);
        self::assertSame('[REDACTED]', $timeline->artifacts[4]['artifact']['locator']);
        self::assertSame(ArtifactAccessClassification::Public->value, $timeline->artifacts[5]['artifact']['access_classification']);
    }

    #[Test]
    public function duplicate_delivery_is_idempotent_for_run_attachments(): void
    {
        $receiver = new ProviderExecutionEventReceiver();
        $log = ProviderExecutionEventLog::begin('provider-invocation:001', 'orbs', 'execution-001', 'run:invocation-001', 'task:invocation-001', 'attempt:001:1');
        $log = $receiver->receive($log, ProviderExecutionEventEnvelope::fromArray(ProviderExecutionEventFixtures::accepted()))->log;

        $first = ProviderExecutionEventFixtures::artifactProduced('evt-artifact-a', 2, 'artifact-dup-1', 'bounded_log', 'store://logs/run-1/chunk-1', 'text/plain', 512, 'sha256:dup');
        $second = ProviderExecutionEventFixtures::artifactProduced('evt-artifact-b', 3, 'artifact-dup-1', 'bounded_log', 'store://logs/run-1/chunk-1', 'text/plain', 512, 'sha256:dup');
        $log = $receiver->receive($log, ProviderExecutionEventEnvelope::fromArray($first))->log;
        $log = $receiver->receive($log, ProviderExecutionEventEnvelope::fromArray($second))->log;

        self::assertCount(3, $log->events);
        self::assertCount(1, $log->artifactAttachments);
    }

    #[Test]
    public function it_makes_hash_mismatch_and_missing_storage_explicit(): void
    {
        $receiver = new ProviderExecutionEventReceiver();
        $log = ProviderExecutionEventLog::begin('provider-invocation:001', 'orbs', 'execution-001', 'run:invocation-001', 'task:invocation-001', 'attempt:001:1');
        $log = $receiver->receive($log, ProviderExecutionEventEnvelope::fromArray(ProviderExecutionEventFixtures::accepted()))->log;

        $mismatch = ProviderExecutionEventFixtures::artifactProduced('evt-artifact-mismatch', 2, 'artifact-mismatch-1', 'diff', 'store://diffs/patch.diff', 'text/x-diff', 123, 'sha256:expected', [
            'integrity_status' => 'hash_mismatch',
            'observed_integrity' => 'sha256:observed',
        ]);
        $missing = ProviderExecutionEventFixtures::artifactProduced('evt-artifact-missing', 3, 'artifact-missing-1', 'screenshot', 'store://shots/missing.png', 'image/png', 456, 'sha256:missing', [
            'storage_status' => 'missing',
            'storage_failure' => 'object missing from storage backend',
        ]);

        $log = $receiver->receive($log, ProviderExecutionEventEnvelope::fromArray($mismatch))->log;
        $log = $receiver->receive($log, ProviderExecutionEventEnvelope::fromArray($missing))->log;

        self::assertSame(RunArtifactAttachmentStatus::HashMismatch, $log->artifactAttachments['artifact-mismatch-1']->status);
        self::assertSame('sha256:observed', $log->artifactAttachments['artifact-mismatch-1']->observedIntegrity);
        self::assertSame(RunArtifactAttachmentStatus::StorageMissing, $log->artifactAttachments['artifact-missing-1']->status);
        self::assertSame('object missing from storage backend', $log->artifactAttachments['artifact-missing-1']->storageFailure);
    }

    #[Test]
    public function immutable_artifacts_cannot_be_silently_replaced_but_supersession_is_supported(): void
    {
        $receiver = new ProviderExecutionEventReceiver();
        $log = ProviderExecutionEventLog::begin('provider-invocation:001', 'orbs', 'execution-001', 'run:invocation-001', 'task:invocation-001', 'attempt:001:1');
        $log = $receiver->receive($log, ProviderExecutionEventEnvelope::fromArray(ProviderExecutionEventFixtures::accepted()))->log;

        $original = ProviderExecutionEventFixtures::artifactProduced('evt-artifact-orig', 2, 'artifact-immutable-1', 'test_result', 'store://tests/results-1.json', 'application/json', 90, 'sha256:original');
        $replacement = ProviderExecutionEventFixtures::artifactProduced('evt-artifact-replace', 3, 'artifact-immutable-1', 'test_result', 'store://tests/results-1.json', 'application/json', 90, 'sha256:changed');
        $superseding = ProviderExecutionEventFixtures::artifactProduced('evt-artifact-supersede', 4, 'artifact-immutable-2', 'test_result', 'store://tests/results-2.json', 'application/json', 91, 'sha256:updated', [
            'supersedes_artifact_id' => 'artifact-immutable-1',
        ]);

        $log = $receiver->receive($log, ProviderExecutionEventEnvelope::fromArray($original))->log;
        try {
            $receiver->receive($log, ProviderExecutionEventEnvelope::fromArray($replacement));
            self::fail('Immutable artifacts should not be silently replaced.');
        } catch (InvalidArgumentException) {
            self::assertTrue(true);
        }

        $log = $receiver->receive($log, ProviderExecutionEventEnvelope::fromArray($superseding))->log;
        self::assertSame('artifact-immutable-1', $log->artifactAttachments['artifact-immutable-2']->artifact->supersedesArtifactId);
    }
}
