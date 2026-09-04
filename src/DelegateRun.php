<?php

declare(strict_types=1);

namespace Sifrious\Logres;

/**
 * Standard host operation for atomically persisting a delegation edge and its
 * canonical child Run/ExecutionState. Implementations arrive with MME-1007.
 */
interface DelegateRun
{
    public function delegate(DelegationRequest $request): DelegationReadModel;
}
