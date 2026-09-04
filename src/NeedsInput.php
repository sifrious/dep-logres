<?php

declare(strict_types=1);

namespace Sifrious\Logres;

use DateTimeImmutable;
use InvalidArgumentException;
use RuntimeException;

final class NeedsInput extends RuntimeException
{
    public function __construct(
        public readonly string $prompt,
        public readonly array $allowedResponses,
        public readonly string $resumeToken,
    ) {
        if (trim($this->prompt) === '' || trim($this->resumeToken) === '') {
            throw new InvalidArgumentException('A human gate requires a prompt and resume token.');
        }

        $invalidResponse = array_filter(
            $this->allowedResponses,
            static fn (mixed $response): bool => ! is_string($response) || trim($response) === '',
        );

        if ($this->allowedResponses === [] || $invalidResponse !== [] || $this->allowedResponses !== array_values(array_unique($this->allowedResponses))) {
            throw new InvalidArgumentException('A human gate requires distinct allowed responses.');
        }

        parent::__construct($this->prompt);
    }

    public function payload(): array
    {
        return [
            'status' => RunStatus::NeedsInput->value,
            'question_id' => $this->resumeToken,
            'prompt' => $this->prompt,
            'allowed_responses' => $this->allowedResponses,
            'response_shape' => [
                'type' => 'string',
                'enum' => $this->allowedResponses,
            ],
            'resume_token' => $this->resumeToken,
        ];
    }

    public function toQuestion(AttemptId $attemptId, string $stepId, DateTimeImmutable $requestedAt, ?DateTimeImmutable $expiresAt = null): HumanInputQuestion
    {
        return new HumanInputQuestion(
            $this->resumeToken,
            $attemptId,
            $stepId,
            $this->prompt,
            $this->allowedResponses,
            $requestedAt,
            $expiresAt,
        );
    }
}
