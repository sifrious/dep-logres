<?php

declare(strict_types=1);

namespace Sifrious\Logres;

final readonly class ProviderExecutionEventReceiver
{
    public function receive(ProviderExecutionEventLog $log, ProviderExecutionEventEnvelope $envelope): ProviderExecutionEventReceipt
    {
        if (! $log->expectsAssociation($envelope)) {
            return new ProviderExecutionEventReceipt(ProviderExecutionEventStatus::Forged, $log);
        }

        $identity = $envelope->stableIdentity();
        if ($log->hasEventIdentity($identity)) {
            return new ProviderExecutionEventReceipt(ProviderExecutionEventStatus::Duplicate, $log->appendEnvelopeOnly($envelope));
        }
        if ($log->hasSequence($envelope->sequence)) {
            return new ProviderExecutionEventReceipt(ProviderExecutionEventStatus::Forged, $log->appendEnvelopeOnly($envelope));
        }

        $normalization = $this->normalize($envelope);
        $event = new ExecutionEvent(
            sequence: $envelope->sequence,
            type: $normalization['type']->value,
            occurredAt: $envelope->occurredAt,
            payload: $normalization['payload'],
            provenance: [
                'provider' => $envelope->provider,
                'provider_execution_id' => $envelope->providerExecutionId->value,
                'provider_event_id' => $envelope->eventId,
                'provider_event_type' => $envelope->providerEventType,
                'signature' => $envelope->signature,
            ],
        );

        $next = $log->appendEnvelopeAndEvent($envelope, $event, $identity);

        if ($next->terminalSequence !== null && $envelope->sequence > $next->terminalSequence) {
            return new ProviderExecutionEventReceipt(ProviderExecutionEventStatus::Late, $next, $event);
        }

        if ($envelope->sequence > $log->highestSequence + 1) {
            return new ProviderExecutionEventReceipt(ProviderExecutionEventStatus::GapDetected, $next, $event);
        }

        if ($envelope->sequence <= $log->highestSequence) {
            return new ProviderExecutionEventReceipt(ProviderExecutionEventStatus::Reordered, $next, $event);
        }

        if ($normalization['unknown']) {
            return new ProviderExecutionEventReceipt(ProviderExecutionEventStatus::UnknownType, $next, $event);
        }

        return new ProviderExecutionEventReceipt(ProviderExecutionEventStatus::Accepted, $next, $event);
    }

    /** @return array{type: ExecutionEventType, payload: array<string, mixed>, unknown: bool} */
    private function normalize(ProviderExecutionEventEnvelope $envelope): array
    {
        $providerType = strtolower(trim($envelope->providerEventType));
        $providerType = str_replace(['-', '.', ' '], '_', $providerType);
        $type = match ($providerType) {
            'target_accepted', 'accepted' => ExecutionEventType::TargetAccepted,
            'agent_started', 'starting', 'started' => ExecutionEventType::AgentStarted,
            'agent_message', 'message' => ExecutionEventType::AgentMessage,
            'progress', 'status' => ExecutionEventType::Progress,
            'tool_invoked', 'tool_call_started' => ExecutionEventType::ToolInvoked,
            'tool_completed', 'tool_call_completed' => ExecutionEventType::ToolCompleted,
            'command_executed', 'command' => ExecutionEventType::CommandExecuted,
            'file_changed', 'file_change' => ExecutionEventType::FileChanged,
            'test_started' => ExecutionEventType::TestStarted,
            'test_completed' => ExecutionEventType::TestCompleted,
            'artifact_produced', 'artifact_reference' => ExecutionEventType::ArtifactProduced,
            'warning' => ExecutionEventType::Warning,
            'input_requested', 'question' => ExecutionEventType::InputRequested,
            'task_completed', 'terminal_result_success' => ExecutionEventType::TaskCompleted,
            'task_failed', 'terminal_result_failure', 'failure' => ExecutionEventType::TaskFailed,
            'task_timed_out', 'timeout' => ExecutionEventType::TaskTimedOut,
            'task_cancelled', 'cancelled' => ExecutionEventType::TaskCancelled,
            default => ExecutionEventType::Warning,
        };
        $unknown = $type === ExecutionEventType::Warning && $providerType !== 'warning';

        $payload = $envelope->payload;
        if ($unknown) {
            $payload['provider_event_type'] = $envelope->providerEventType;
        }

        $reference = $this->referenceFor($type, $payload);
        if ($reference !== null) {
            $payload['reference'] = $reference->toArray();
        }

        return ['type' => $type, 'payload' => $payload, 'unknown' => $unknown];
    }

    /** @param array<string, mixed> $payload */
    private function referenceFor(ExecutionEventType $type, array $payload): ?ExecutionEventReference
    {
        return match ($type) {
            ExecutionEventType::ToolInvoked, ExecutionEventType::ToolCompleted => $this->toolReference($payload),
            ExecutionEventType::CommandExecuted => $this->commandReference($payload),
            ExecutionEventType::FileChanged => $this->fileReference($payload),
            ExecutionEventType::TestStarted, ExecutionEventType::TestCompleted => $this->testReference($payload),
            ExecutionEventType::InputRequested => $this->inputReference($payload),
            ExecutionEventType::ArtifactProduced => $this->artifactReference($payload),
            default => null,
        };
    }

    /** @param array<string, mixed> $payload */
    private function toolReference(array $payload): ?ExecutionEventReference
    {
        $invocationId = $payload['tool_invocation_id'] ?? $payload['invocation_id'] ?? null;
        $toolName = $payload['tool_name'] ?? $payload['name'] ?? null;
        if (! is_string($invocationId) || ! is_string($toolName)) {
            return null;
        }
        return new ToolInvocationReference($invocationId, $toolName);
    }

    /** @param array<string, mixed> $payload */
    private function commandReference(array $payload): ?ExecutionEventReference
    {
        $commandId = $payload['command_id'] ?? null;
        $command = $payload['command'] ?? null;
        if (! is_string($commandId) || ! is_string($command)) {
            return null;
        }
        return new CommandExecutionReference($commandId, $command);
    }

    /** @param array<string, mixed> $payload */
    private function fileReference(array $payload): ?ExecutionEventReference
    {
        $path = $payload['path'] ?? null;
        $changeType = $payload['change_type'] ?? $payload['change'] ?? null;
        if (! is_string($path) || ! is_string($changeType)) {
            return null;
        }
        return new FileChangeReference($path, $changeType);
    }

    /** @param array<string, mixed> $payload */
    private function testReference(array $payload): ?ExecutionEventReference
    {
        $suite = $payload['suite'] ?? null;
        $name = $payload['name'] ?? null;
        if (! is_string($suite) || ! is_string($name)) {
            return null;
        }
        return new TestExecutionReference($suite, $name);
    }

    /** @param array<string, mixed> $payload */
    private function inputReference(array $payload): ?ExecutionEventReference
    {
        $requestId = $payload['input_request_id'] ?? $payload['request_id'] ?? null;
        $prompt = $payload['prompt'] ?? null;
        if (! is_string($requestId) || ! is_string($prompt)) {
            return null;
        }
        return new InputRequestReference($requestId, $prompt);
    }

    /** @param array<string, mixed> $payload */
    private function artifactReference(array $payload): ?ExecutionEventReference
    {
        $id = $payload['id'] ?? null;
        $kind = $payload['kind'] ?? null;
        $path = $payload['path'] ?? null;
        $mediaType = $payload['media_type'] ?? $payload['mediaType'] ?? null;
        $size = $payload['size'] ?? null;
        $hash = $payload['hash'] ?? null;
        if (! is_string($id) || ! is_string($kind) || ! is_string($path) || ! is_string($mediaType) || ! is_int($size) || ! is_string($hash)) {
            return null;
        }

        return new class(new ArtifactReference($id, $kind, $path, $mediaType, $size, $hash)) implements ExecutionEventReference {
            public function __construct(private readonly ArtifactReference $artifact) {}
            public function toArray(): array
            {
                return [
                    'kind' => 'artifact',
                    'id' => $this->artifact->id,
                    'artifact_kind' => $this->artifact->kind,
                    'path' => $this->artifact->path,
                    'media_type' => $this->artifact->mediaType,
                    'size' => $this->artifact->size,
                    'hash' => $this->artifact->hash,
                ];
            }
        };
    }
}
