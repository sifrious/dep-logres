<?php

declare(strict_types=1);

namespace Sifrious\Logres;

use InvalidArgumentException;

final readonly class ExecutionTargetId
{
    public function __construct(public string $value)
    {
        if (preg_match('/^target:[a-z][a-z0-9._-]*:[a-zA-Z0-9._:-]+$/', $value) !== 1) {
            throw new InvalidArgumentException('An execution target identity requires provider and stable provider identity namespaces.');
        }
    }
}
