<?php

declare(strict_types=1);

namespace Sifrious\Logres;

use InvalidArgumentException;

final readonly class TaskId
{
    public function __construct(public string $value)
    {
        if (preg_match('/^task:[a-zA-Z0-9._:-]+$/', $value) !== 1) {
            throw new InvalidArgumentException('A task identity must use the task namespace.');
        }
    }
}
