<?php

declare(strict_types=1);

namespace Sifrious\Logres\Tests;

use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sifrious\Logres\ArtifactAccessClassification;
use Sifrious\Logres\ArtifactReference;
use Sifrious\Logres\ExecutionEvent;
use Sifrious\Logres\RunId;

final class ExecutionRecordTest extends TestCase
{
    #[Test]
    public function an_event_serializes_append_oriented_facts(): void
    {
        $event = new ExecutionEvent(
            3,
            'stdout',
            new DateTimeImmutable('2026-08-27T12:34:56+00:00'),
            ['chunk' => "one\ntwo"],
            ['harness_id' => 'fixture'],
        );

        self::assertSame([
            'sequence' => 3,
            'type' => 'stdout',
            'occurred_at' => '2026-08-27T12:34:56+00:00',
            'payload' => ['chunk' => "one\ntwo"],
            'provenance' => ['harness_id' => 'fixture'],
            'execution_identity' => [
                'classification' => 'legacy_missing',
                'workspace_id' => null,
            ],
        ], $event->toArray());
    }

    #[Test]
    public function an_event_sequence_starts_at_one(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new ExecutionEvent(0, 'started', new DateTimeImmutable());
    }

    #[Test]
    public function an_artifact_retains_verification_facts(): void
    {
        $artifact = new ArtifactReference(
            id: 'artifact-01',
            runId: new RunId('run:invocation-001'),
            type: 'test_result',
            locator: 'artifacts/report.json',
            mediaType: 'application/json',
            size: 418,
            integrity: 'sha256:123456',
            accessClassification: ArtifactAccessClassification::Internal,
        );

        self::assertSame('artifact-01', $artifact->id);
        self::assertSame('run:invocation-001', $artifact->runId->value);
        self::assertSame('test_result', $artifact->type);
        self::assertSame('artifacts/report.json', $artifact->locator);
        self::assertSame('application/json', $artifact->mediaType);
        self::assertSame(418, $artifact->size);
        self::assertSame('sha256:123456', $artifact->integrity);
    }
}
