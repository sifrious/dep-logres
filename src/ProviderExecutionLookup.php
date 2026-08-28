<?php

declare(strict_types=1);

namespace Sifrious\Logres;

interface ProviderExecutionLookup
{
    public function find(Run $run): ProviderExecutionLookupResult;
}
