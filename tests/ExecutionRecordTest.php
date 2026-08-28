<?php

declare(strict_types=1);

namespace Sifrious\Logres\Tests;

use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sifrious\Logres\ArtifactReference;
use Sifrious\Logres\ExecutionEvent;

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
            'artifact-01',
            'report',
            'artifacts/report.json',
            'application/json',
            418,
            'sha256:123456',
        );

        self::assertSame('artifact-01', $artifact->id);
        self::assertSame('report', $artifact->kind);
        self::assertSame('artifacts/report.json', $artifact->path);
        self::assertSame('application/json', $artifact->mediaType);
        self::assertSame(418, $artifact->size);
        self::assertSame('sha256:123456', $artifact->hash);
    }
}
