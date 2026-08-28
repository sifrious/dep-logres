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
    ) {
        if (trim($this->harnessId) === '') {
            throw new InvalidArgumentException('A harness ID is required.');
        }

        if (trim($this->workspace) === '') {
            throw new InvalidArgumentException('A workspace reference is required.');
        }
    }
}
