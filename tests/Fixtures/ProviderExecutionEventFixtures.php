<?php

declare(strict_types=1);

namespace Sifrious\Logres\Tests\Fixtures;

final class ProviderExecutionEventFixtures
{
    /** @return array<string, mixed> */
    public static function accepted(string $id = 'evt-001', int $sequence = 1): array
    {
        return self::base($id, $sequence, 'target.accepted');
    }

    /** @return array<string, mixed> */
    public static function started(string $id = 'evt-002', int $sequence = 2): array
    {
        return self::base($id, $sequence, 'agent.started');
    }

    /** @return array<string, mixed> */
    public static function progress(string $id = 'evt-003', int $sequence = 3): array
    {
        return self::base($id, $sequence, 'progress', ['message' => 'working']);
    }

    /** @return array<string, mixed> */
    public static function completed(string $id = 'evt-004', int $sequence = 4): array
    {
        return self::base($id, $sequence, 'task.completed');
    }

    /** @return array<string, mixed> */
    public static function duplicate(): array
    {
        return self::started('evt-dup', 2);
    }

    /** @return array<string, mixed> */
    public static function reorderedEarly(): array
    {
        return self::progress('evt-reorder-2', 2);
    }

    /** @return array<string, mixed> */
    public static function reorderedLate(): array
    {
        return self::accepted('evt-reorder-1', 1);
    }

    /** @return array<string, mixed> */
    public static function lateAfterTerminal(): array
    {
        return self::progress('evt-late', 5);
    }

    /** @return array<string, mixed> */
    public static function unknownType(): array
    {
        return self::base('evt-unknown', 2, 'provider.unknown.event', ['detail' => 'opaque']);
    }

    /** @return array<string, mixed> */
    public static function forgedRun(): array
    {
        $event = self::started('evt-forged', 2);
        $event['run_id'] = 'run:forged';

        return $event;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function missingSequenceGap(): array
    {
        return [
            self::accepted('evt-gap-1', 1),
            self::progress('evt-gap-3', 3),
        ];
    }

    /** @return array<string, mixed> */
    private static function base(string $id, int $sequence, string $type, array $payload = []): array
    {
        return [
            'event_id' => $id,
            'provider' => 'orbs',
            'provider_execution_id' => 'execution-001',
            'invocation_id' => 'provider-invocation:001',
            'run_id' => 'run:invocation-001',
            'task_id' => 'task:invocation-001',
            'attempt_id' => 'attempt:001:1',
            'sequence' => $sequence,
            'occurred_at' => '2026-09-01T12:00:00Z',
            'event_type' => $type,
            'payload' => $payload,
            'signature' => 'fixture-signed',
        ];
    }
}
