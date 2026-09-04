<?php

declare(strict_types=1);

namespace Sifrious\Logres\Tests\Fixtures;

use LogicException;
use Sifrious\Logres\TurnCheckpoint;
use Sifrious\Logres\TurnCheckpointStore;
use Sifrious\ReferenceContract\CrossPackageReference;

final class InMemoryTurnCheckpointStore implements TurnCheckpointStore
{
    /** @var array<string, TurnCheckpoint> */
    private array $checkpoints = [];

    public function save(TurnCheckpoint $checkpoint): void
    {
        $key = $checkpoint->reference()->key();
        if (isset($this->checkpoints[$key]) && $this->checkpoints[$key] != $checkpoint) {
            throw new LogicException('A Turn checkpoint reference cannot be reused with different state.');
        }
        $this->checkpoints[$key] = $checkpoint;
    }

    public function find(CrossPackageReference $reference): ?TurnCheckpoint
    {
        return $this->checkpoints[$reference->key()] ?? null;
    }
}
