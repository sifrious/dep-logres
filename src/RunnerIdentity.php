<?php

declare(strict_types=1);

namespace Sifrious\Logres;

use InvalidArgumentException;

final readonly class RunnerIdentity
{
    public function __construct(public string $value)
    {
        if (preg_match('/^runner:[a-zA-Z0-9._:-]+$/', $value) !== 1) {
            throw new InvalidArgumentException('Runner identity must use the runner: namespace.');
        }
    }

    public function asExecutionNode(): ExecutionNodeRef
    {
        return new ExecutionNodeRef($this->value);
    }
}
