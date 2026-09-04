<?php

declare(strict_types=1);

namespace Sifrious\Logres;

use Sifrious\ReferenceContract\CrossPackageReference;

interface TurnCheckpointStore
{
    /** Exact replay of the same checkpoint must be idempotent; conflicting reuse must fail. */
    public function save(TurnCheckpoint $checkpoint): void;

    public function find(CrossPackageReference $reference): ?TurnCheckpoint;
}
