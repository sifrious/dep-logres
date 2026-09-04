<?php

declare(strict_types=1);

namespace Sifrious\Logres;

use DateTimeImmutable;
use InvalidArgumentException;

final readonly class HumanInputEvent
{
    public function __construct(
        public string $operationId,
        public string $type,
        public DateTimeImmutable $occurredAt,
        public ?string $actorId = null,
        public ?string $channel = null,
    ) {
        if (trim($this->operationId) === '' || trim($this->type) === '') {
            throw new InvalidArgumentException('A human-input audit event requires operation identity and type.');
        }
    }
}
