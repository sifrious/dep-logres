<?php

declare(strict_types=1);

namespace Sifrious\Logres;

use InvalidArgumentException;

final readonly class ExecutionRequestId
{
    public function __construct(public string $value)
    {
        if (preg_match('/^request:[a-zA-Z0-9._-]+$/', $value) !== 1) {
            throw new InvalidArgumentException('An execution request ID must use the request: namespace.');
        }
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
