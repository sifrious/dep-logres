<?php

declare(strict_types=1);

namespace Sifrious\Logres;

use DomainException;

final class RunIdentityConflict extends DomainException
{
    public static function run(RunId $id): self
    {
        return new self("Run identity {$id->value} already exists.");
    }

    public static function providerExecution(ProviderExecutionId $id): self
    {
        return new self("Provider execution identity {$id->canonical()} already belongs to another Run.");
    }
}
