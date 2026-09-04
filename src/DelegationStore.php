<?php

declare(strict_types=1);

namespace Sifrious\Logres;

interface DelegationStore
{
    /**
     * Persist exactly once. Implementations must reject reuse of an operation
     * identity, delegation identity, or child Run identity with different data.
     */
    public function create(DelegationRequest $request): void;

    public function find(DelegationId $id): ?DelegationRequest;

    /** @return list<DelegationRequest> */
    public function childrenOf(RunId $parentRunId): array;

    public function parentOf(RunId $childRunId): ?DelegationRequest;
}
