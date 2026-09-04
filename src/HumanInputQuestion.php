<?php

declare(strict_types=1);

namespace Sifrious\Logres;

use DateTimeImmutable;
use InvalidArgumentException;

final readonly class HumanInputQuestion
{
    /** @param list<string> $allowedResponses */
    public function __construct(
        public string $id,
        public AttemptId $attemptId,
        public string $stepId,
        public string $prompt,
        public array $allowedResponses,
        public DateTimeImmutable $requestedAt,
        public ?DateTimeImmutable $expiresAt = null,
    ) {
        if (trim($this->id) === '' || trim($this->stepId) === '' || trim($this->prompt) === '') {
            throw new InvalidArgumentException('A human-input question requires identity, step identity, and a prompt.');
        }

        $invalid = array_filter(
            $this->allowedResponses,
            static fn (mixed $response): bool => ! is_string($response) || trim($response) === '',
        );
        if ($this->allowedResponses === [] || $invalid !== [] || $this->allowedResponses !== array_values(array_unique($this->allowedResponses))) {
            throw new InvalidArgumentException('A human-input question requires distinct non-empty string responses.');
        }
        if ($this->expiresAt !== null && $this->expiresAt <= $this->requestedAt) {
            throw new InvalidArgumentException('A human-input expiry must follow its request time.');
        }
    }

    /** @return array{type: string, enum: list<string>} */
    public function responseShape(): array
    {
        return ['type' => 'string', 'enum' => $this->allowedResponses];
    }
}
