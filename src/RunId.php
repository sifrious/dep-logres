<?php

declare(strict_types=1);

namespace Sifrious\Logres;

use InvalidArgumentException;

final readonly class RunId
{
    public function __construct(public string $value)
    {
        if (preg_match('/^run:[a-zA-Z0-9._-]+$/', $value) !== 1) {
            throw new InvalidArgumentException('A Run identity must use the run: namespace.');
        }
    }
}
