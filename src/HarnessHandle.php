<?php

declare(strict_types=1);

namespace Sifrious\Logres;

use DateTimeImmutable;
use InvalidArgumentException;

final readonly class HarnessHandle
{
    public function __construct(
        public string $attemptId,
        public string $harnessId,
        public DateTimeImmutable $startedAt,
    ) {
        if (trim($this->attemptId) === '') {
            throw new InvalidArgumentException('A harness attempt ID is required.');
        }

        if (trim($this->harnessId) === '') {
            throw new InvalidArgumentException('A harness ID is required.');
        }
    }
}
