<?php

declare(strict_types=1);

namespace Sifrious\Logres;

interface ExecutionStateStore
{
    public function create(ExecutionState $state): void;

    public function find(RunId $runId): ?ExecutionState;

    /** Atomically persist $next only when the stored version equals $expectedVersion. */
    public function compareAndSwap(RunId $runId, int $expectedVersion, ExecutionState $next): bool;
}
