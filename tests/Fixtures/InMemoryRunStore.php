<?php

declare(strict_types=1);

namespace Sifrious\Logres\Tests\Fixtures;

use Sifrious\Logres\ProviderExecutionId;
use Sifrious\Logres\Run;
use Sifrious\Logres\RunId;
use Sifrious\Logres\RunIdentityConflict;
use Sifrious\Logres\RunStore;

final class InMemoryRunStore implements RunStore
{
    private array $runs = [];

    public function create(Run $run): void
    {
        if (isset($this->runs[$run->id->value])) {
            throw RunIdentityConflict::run($run->id);
        }

        $this->save($run);
    }

    public function save(Run $run): void
    {
        if ($run->providerExecutionId !== null) {
            $owner = $this->findByProviderExecutionId($run->providerExecutionId);

            if ($owner !== null && $owner->id->value !== $run->id->value) {
                throw RunIdentityConflict::providerExecution($run->providerExecutionId);
            }
        }

        $this->runs[$run->id->value] = $run;
    }

    public function find(RunId $id): ?Run
    {
        return $this->runs[$id->value] ?? null;
    }

    public function findByProviderExecutionId(ProviderExecutionId $providerExecutionId): ?Run
    {
        foreach ($this->runs as $run) {
            if ($run->providerExecutionId?->canonical() === $providerExecutionId->canonical()) {
                return $run;
            }
        }

        return null;
    }
}
