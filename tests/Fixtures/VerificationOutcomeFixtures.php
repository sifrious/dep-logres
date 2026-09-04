<?php

declare(strict_types=1);

namespace Sifrious\Logres\Tests\Fixtures;

use Sifrious\Logres\CheckDefinition;
use Sifrious\Logres\ExecutionEventType;
use Sifrious\Logres\ProviderExecutionEventEnvelope;
use Sifrious\Logres\ProviderExecutionEventLog;
use Sifrious\Logres\ProviderExecutionEventReceiver;
use Sifrious\Logres\VerificationPlan;

final class VerificationOutcomeFixtures
{
    public static function providerClaimsSuccessRequiredTestFailsPlan(): VerificationPlan
    {
        return new VerificationPlan('plan:required-test', '1', [
            new CheckDefinition(
                id: 'check:test:critical',
                name: 'Critical acceptance test',
                eventType: ExecutionEventType::TestCompleted,
                referenceCriteria: [
                    'kind' => 'test_execution',
                    'suite' => 'acceptance',
                    'name' => 'critical-flow',
                ],
            ),
        ]);
    }

    public static function providerClaimsSuccessRequiredTestFailsLog(): ProviderExecutionEventLog
    {
        return self::normalizedLog([
            self::event('evt-1', 1, 'target.accepted'),
            self::event('evt-2', 2, 'test.completed', [
                'suite' => 'acceptance',
                'name' => 'critical-flow',
                'status' => 'failed',
                'exit_code' => 1,
                'duration_ms' => 742,
                'output' => '1 failed assertion',
                'tool_version' => 'phpunit-11.4',
            ]),
            self::event('evt-3', 3, 'task.completed'),
        ]);
    }

    public static function noChangeExpectedPlan(): VerificationPlan
    {
        return new VerificationPlan('plan:no-change', '1', [
            new CheckDefinition(
                id: 'check:command:no-change',
                name: 'No tracked changes expected',
                eventType: ExecutionEventType::CommandExecuted,
                referenceCriteria: [
                    'kind' => 'command_execution',
                    'command_id' => 'cmd:no-change',
                ],
            ),
        ]);
    }

    public static function noChangeExpectedLog(): ProviderExecutionEventLog
    {
        return self::normalizedLog([
            self::event('evt-1', 1, 'target.accepted'),
            self::event('evt-2', 2, 'command.executed', [
                'command_id' => 'cmd:no-change',
                'command' => 'git diff --exit-code',
                'exit_code' => 0,
                'duration_ms' => 21,
                'output' => '',
            ]),
            self::event('evt-3', 3, 'task.completed'),
        ]);
    }

    public static function commitProducedVerificationFailedPlan(): VerificationPlan
    {
        return new VerificationPlan('plan:commit-failed', '1', [
            new CheckDefinition(
                id: 'check:command:commit-produced',
                name: 'Commit produced',
                eventType: ExecutionEventType::CommandExecuted,
                referenceCriteria: [
                    'kind' => 'command_execution',
                    'command_id' => 'cmd:commit',
                ],
            ),
            new CheckDefinition(
                id: 'check:test:regression',
                name: 'Regression test passes',
                eventType: ExecutionEventType::TestCompleted,
                referenceCriteria: [
                    'kind' => 'test_execution',
                    'suite' => 'verification',
                    'name' => 'regression-suite',
                ],
            ),
        ]);
    }

    public static function commitProducedVerificationFailedLog(): ProviderExecutionEventLog
    {
        return self::normalizedLog([
            self::event('evt-1', 1, 'target.accepted'),
            self::event('evt-2', 2, 'command.executed', [
                'command_id' => 'cmd:commit',
                'command' => 'git commit -m "apply change"',
                'exit_code' => 0,
                'duration_ms' => 55,
                'output' => '[main abc123] apply change',
            ]),
            self::event('evt-3', 3, 'test.completed', [
                'suite' => 'verification',
                'name' => 'regression-suite',
                'status' => 'failed',
                'exit_code' => 1,
                'duration_ms' => 980,
                'output' => 'regression failed',
            ]),
            self::event('evt-4', 4, 'task.completed'),
        ]);
    }

    public static function unavailableCheckPlan(): VerificationPlan
    {
        return new VerificationPlan('plan:tool-unavailable', '1', [
            new CheckDefinition(
                id: 'check:tool:policy',
                name: 'Required static-analysis tool available',
                eventType: ExecutionEventType::ToolCompleted,
                referenceCriteria: [
                    'kind' => 'tool_invocation',
                    'invocation_id' => 'tool:phpstan',
                    'tool_name' => 'phpstan',
                ],
            ),
        ]);
    }

    public static function unavailableCheckLog(): ProviderExecutionEventLog
    {
        return self::normalizedLog([
            self::event('evt-1', 1, 'target.accepted'),
            self::event('evt-2', 2, 'tool.completed', [
                'tool_invocation_id' => 'tool:phpstan',
                'tool_name' => 'phpstan',
                'status' => 'unavailable',
                'output' => 'binary missing',
                'duration_ms' => 14,
            ]),
            self::event('evt-3', 3, 'task.completed'),
        ]);
    }

    /** @param list<array<string, mixed>> $envelopes */
    private static function normalizedLog(array $envelopes): ProviderExecutionEventLog
    {
        $receiver = new ProviderExecutionEventReceiver();
        $log = ProviderExecutionEventLog::begin('provider-invocation:001', 'orbs', 'execution-001', 'run:invocation-001', 'task:invocation-001', 'attempt:001:1');
        foreach ($envelopes as $envelope) {
            $log = $receiver->receive($log, ProviderExecutionEventEnvelope::fromArray($envelope))->log;
        }

        return $log;
    }

    /** @param array<string, mixed> $payload @return array<string, mixed> */
    private static function event(string $id, int $sequence, string $type, array $payload = []): array
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
            'occurred_at' => '2026-09-02T10:00:00Z',
            'event_type' => $type,
            'payload' => $payload,
            'signature' => 'fixture-signature',
        ];
    }
}
