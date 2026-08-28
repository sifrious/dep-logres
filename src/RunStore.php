<?php

declare(strict_types=1);

namespace Sifrious\Logres;

interface RunStore
{
    public function create(Run $run): void;

    public function save(Run $run): void;

    public function find(RunId $id): ?Run;

    public function findByProviderExecutionId(ProviderExecutionId $providerExecutionId): ?Run;
}
