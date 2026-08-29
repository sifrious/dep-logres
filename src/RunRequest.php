<?php

declare(strict_types=1);

namespace Sifrious\Logres;

use InvalidArgumentException;

final readonly class RunRequest
{
    public function __construct(
        public Turn $turn,
        public string $harnessId,
        public string $workspace,
        public ?string $idempotencyKey = null,
    ) {
        if (trim($this->harnessId) === '') {
            throw new InvalidArgumentException('A harness ID is required.');
        }

        if (trim($this->workspace) === '') {
            throw new InvalidArgumentException('A workspace reference is required.');
        }

        if ($this->idempotencyKey !== null && trim($this->idempotencyKey) === '') {
            throw new InvalidArgumentException('An idempotency key cannot be empty.');
        }
    }

    public function identity(): string
    {
        return $this->idempotencyKey ?? hash('sha256', $this->harnessId."\0".$this->workspace."\0".$this->turn->prompt);
    }
}
