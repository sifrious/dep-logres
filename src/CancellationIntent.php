<?php

declare(strict_types=1);

namespace Sifrious\Logres;

use DateTimeImmutable;
use InvalidArgumentException;

final readonly class CancellationIntent
{
    public function __construct(
        public string $operationId,
        public CancellationKind $kind,
        public string $requestedBy,
        public string $reason,
        public CancellationStatus $status,
        public DateTimeImmutable $requestedAt,
        public ?DateTimeImmutable $confirmedAt = null,
        public ?string $partialResultReference = null,
    ) {
        if (trim($operationId) === '' || trim($requestedBy) === '' || trim($reason) === '') {
            throw new InvalidArgumentException('Cancellation intent requires operation, actor, and reason.');
        }
        if (($status === CancellationStatus::Confirmed) !== ($confirmedAt !== null)) {
            throw new InvalidArgumentException('Confirmed cancellation requires its confirmation time.');
        }
    }

    public function confirm(DateTimeImmutable $at, ?string $partialResultReference): self
    {
        if ($this->status === CancellationStatus::Confirmed) {
            return $this;
        }
        return new self($this->operationId, $this->kind, $this->requestedBy, $this->reason, CancellationStatus::Confirmed, $this->requestedAt, $at, $partialResultReference);
    }
}
