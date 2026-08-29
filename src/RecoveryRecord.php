<?php

declare(strict_types=1);

namespace Sifrious\Logres;

use DateTimeImmutable;
use InvalidArgumentException;

final readonly class RecoveryRecord
{
    public function __construct(
        public string $operationId,
        public AttemptId $attemptId,
        public FailureClassification $classification,
        public RecoveryAction $action,
        public string $reason,
        public DateTimeImmutable $observedAt,
    ) {
        if (trim($operationId) === '' || trim($reason) === '') {
            throw new InvalidArgumentException('Recovery evidence requires operation identity and reason.');
        }
    }
}
