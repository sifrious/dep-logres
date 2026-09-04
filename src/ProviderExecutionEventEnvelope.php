<?php

declare(strict_types=1);

namespace Sifrious\Logres;

use DateTimeImmutable;
use InvalidArgumentException;

final readonly class ProviderExecutionEventEnvelope
{
    /** @param array<string, mixed> $payload */
    public function __construct(
        public string $eventId,
        public string $provider,
        public ProviderExecutionId $providerExecutionId,
        public string $invocationId,
        public RunId $runId,
        public TaskId $taskId,
        public AttemptId $attemptId,
        public int $sequence,
        public DateTimeImmutable $occurredAt,
        public string $providerEventType,
        public array $payload = [],
        public ?string $signature = null,
    ) {
        if (trim($eventId) === '' || trim($invocationId) === '' || trim($providerEventType) === '' || $sequence < 1) {
            throw new InvalidArgumentException('Execution event envelopes require identity, event type, and positive sequence.');
        }
        if ($providerExecutionId->provider !== $provider) {
            throw new InvalidArgumentException('Provider execution identity must match the envelope provider.');
        }
    }

    /** @param array<string, mixed> $input */
    public static function fromArray(array $input): self
    {
        foreach (['event_id', 'provider', 'provider_execution_id', 'invocation_id', 'run_id', 'task_id', 'attempt_id', 'sequence', 'occurred_at', 'event_type'] as $field) {
            if (! array_key_exists($field, $input)) {
                throw new InvalidArgumentException("Missing provider event envelope field [{$field}].");
            }
        }

        return new self(
            eventId: (string) $input['event_id'],
            provider: (string) $input['provider'],
            providerExecutionId: new ProviderExecutionId((string) $input['provider'], (string) $input['provider_execution_id']),
            invocationId: (string) $input['invocation_id'],
            runId: new RunId((string) $input['run_id']),
            taskId: new TaskId((string) $input['task_id']),
            attemptId: new AttemptId((string) $input['attempt_id']),
            sequence: (int) $input['sequence'],
            occurredAt: new DateTimeImmutable((string) $input['occurred_at']),
            providerEventType: (string) $input['event_type'],
            payload: is_array($input['payload'] ?? null) ? $input['payload'] : [],
            signature: isset($input['signature']) ? (string) $input['signature'] : null,
        );
    }

    public function stableIdentity(): string
    {
        return $this->providerExecutionId->canonical().'#'.$this->eventId;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'event_id' => $this->eventId,
            'provider' => $this->provider,
            'provider_execution_id' => $this->providerExecutionId->value,
            'invocation_id' => $this->invocationId,
            'run_id' => $this->runId->value,
            'task_id' => $this->taskId->value,
            'attempt_id' => $this->attemptId->value,
            'sequence' => $this->sequence,
            'occurred_at' => $this->occurredAt->format(DATE_ATOM),
            'event_type' => $this->providerEventType,
            'payload' => $this->payload,
            'signature' => $this->signature,
        ];
    }
}
