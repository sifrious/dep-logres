<?php

declare(strict_types=1);

namespace Sifrious\Logres;

use InvalidArgumentException;

final readonly class TaskPlanId
{
    public function __construct(public string $value)
    {
        if (preg_match('/^plan:[a-zA-Z0-9._:-]+$/', $value) !== 1) {
            throw new InvalidArgumentException('A task-plan identity must use the plan namespace.');
        }
    }
}
