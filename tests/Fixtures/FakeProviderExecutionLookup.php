<?php

declare(strict_types=1);

namespace Sifrious\Logres\Tests\Fixtures;

use Sifrious\Logres\ProviderExecutionLookup;
use Sifrious\Logres\ProviderExecutionLookupResult;
use Sifrious\Logres\Run;

final readonly class FakeProviderExecutionLookup implements ProviderExecutionLookup
{
    public function __construct(private ProviderExecutionLookupResult $result) {}

    public function find(Run $run): ProviderExecutionLookupResult
    {
        return $this->result;
    }
}
