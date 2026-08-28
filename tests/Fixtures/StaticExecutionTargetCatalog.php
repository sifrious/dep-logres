<?php

declare(strict_types=1);

namespace Sifrious\Logres\Tests\Fixtures;

use Sifrious\Logres\ExecutionTargetCatalog;
use Sifrious\Logres\ExecutionTargetRequirements;

final readonly class StaticExecutionTargetCatalog implements ExecutionTargetCatalog
{
    public function __construct(private array $candidates) {}

    public function discover(ExecutionTargetRequirements $requirements): array
    {
        return $this->candidates;
    }
}
