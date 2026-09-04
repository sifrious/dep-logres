<?php

declare(strict_types=1);

namespace Sifrious\Logres;

use DateTimeImmutable;
use InvalidArgumentException;

final readonly class ExecutionEvent
{
    public function __construct(
        public int $sequence,
        public string $type,
        public DateTimeImmutable $occurredAt,
        public array $payload = [],
        public array $provenance = [],
        public ?StacksExecutionContext $executionIdentity = null,
    ) {
        if ($this->sequence < 1) {
            throw new InvalidArgumentException('An execution event sequence starts at one.');
        }

        if (trim($this->type) === '') {
            throw new InvalidArgumentException('An execution event type is required.');
        }
    }

    public function toArray(): array
    {
        return [
            'sequence' => $this->sequence,
            'type' => $this->type,
            'occurred_at' => $this->occurredAt->format(DATE_ATOM),
            'payload' => $this->payload,
            'provenance' => $this->provenance,
            'execution_identity' => $this->executionIdentity?->toArray()
                ?? ExecutionProvenanceClassification::missingRecord(),
        ];
    }
}
