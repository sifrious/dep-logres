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
    public static function artifactProduced(
        string $id,
        int $sequence,
        string $artifactId,
        string $type,
        string $locator,
        string $mediaType,
        int $size,
        string $integrity,
        array $overrides = [],
    ): array {
        return self::base($id, $sequence, 'artifact.produced', array_merge([
            'id' => $artifactId,
            'type' => $type,
            'locator' => $locator,
            'media_type' => $mediaType,
            'size' => $size,
            'integrity' => $integrity,
            'retention' => 'run-retained',
            'access_classification' => 'internal',
        ], $overrides));
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function artifactManifest(): array
    {
        return [
            self::artifactProduced('evt-artifact-commit', 2, 'artifact-commit-1', 'commit', 'git:sha/0d9d7be', 'text/plain', 40, 'sha1:0d9d7be'),
            self::artifactProduced('evt-artifact-diff', 3, 'artifact-diff-1', 'diff', 'git:diff/0d9d7be..ac14f11', 'text/x-diff', 420, 'sha256:diff420'),
            self::artifactProduced('evt-artifact-log', 4, 'artifact-log-1', 'bounded_log', 'store://logs/run-1/chunk-1', 'text/plain', 512, 'sha256:log512'),
            self::artifactProduced('evt-artifact-test', 5, 'artifact-test-1', 'test_result', 'store://tests/run-1/phpunit.xml', 'application/xml', 801, 'sha256:test801'),
            self::artifactProduced('evt-artifact-shot', 6, 'artifact-shot-1', 'screenshot', 'store://shots/run-1/fail.png', 'image/png', 2213, 'sha256:shot2213', ['access_classification' => 'restricted']),
            self::artifactProduced('evt-artifact-url', 7, 'artifact-url-1', 'external_url', 'https://deploy.example.test/releases/42', 'text/uri-list', 1, 'remote:etag:release-42', ['access_classification' => 'public']),
        ];
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
