<?php

declare(strict_types=1);

namespace Sifrious\Logres;

use InvalidArgumentException;

final readonly class CurrentWorkload
{
    public function __construct(public int $active, public int $capacity)
    {
        if ($active < 0 || $capacity < 1 || $active > $capacity) {
            throw new InvalidArgumentException('Runner workload requires non-negative active work within positive capacity.');
        }
    }
}
