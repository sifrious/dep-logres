<?php

declare(strict_types=1);

namespace Sifrious\Logres;

use InvalidArgumentException;

final readonly class TaskPromptId
{
    public function __construct(public string $value)
    {
        if (preg_match('/^prompt:task:[a-zA-Z0-9._:-]+:v[1-9][0-9]*$/', $value) !== 1) {
            throw new InvalidArgumentException('A task prompt identity must use the prompt:task namespace and include a version.');
        }
    }
}
