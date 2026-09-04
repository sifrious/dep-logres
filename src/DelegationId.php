<?php

declare(strict_types=1);

namespace Sifrious\Logres;

use InvalidArgumentException;

final readonly class DelegationId
{
    public function __construct(public string $value)
    {
        if (preg_match('/^delegation:[a-zA-Z0-9._-]+$/', $value) !== 1) {
            throw new InvalidArgumentException('A delegation identity must use the delegation: namespace.');
        }
    }
}
