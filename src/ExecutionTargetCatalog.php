<?php

declare(strict_types=1);

namespace Sifrious\Logres;

interface ExecutionTargetCatalog
{
    public function discover(ExecutionTargetRequirements $requirements): array;
}
