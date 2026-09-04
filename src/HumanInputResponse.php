<?php

declare(strict_types=1);

namespace Sifrious\Logres;

use DateTimeImmutable;
use InvalidArgumentException;

final readonly class HumanInputResponse
{
    public function __construct(
        public string $id,
        public string $questionId,
        public string $responderId,
        public string $value,
        public DateTimeImmutable $respondedAt,
    ) {
        if (trim($this->id) === '' || trim($this->questionId) === '' || trim($this->responderId) === '' || trim($this->value) === '') {
            throw new InvalidArgumentException('A human-input response requires identity, question, responder, and value.');
        }
    }
}
