<?php

declare(strict_types=1);

namespace Sifrious\Logres;

use RuntimeException;

final class StaleRunnerLease extends RuntimeException
{
    public function __construct(public readonly string $leaseId)
    {
        parent::__construct("Runner lease {$leaseId} is stale.");
    }
}
