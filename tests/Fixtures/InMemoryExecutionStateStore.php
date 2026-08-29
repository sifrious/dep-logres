<?php

declare(strict_types=1);

namespace Sifrious\Logres\Tests\Fixtures;

use InvalidArgumentException;
use Sifrious\Logres\ExecutionState;
use Sifrious\Logres\ExecutionStateStore;
use Sifrious\Logres\RunId;

final class InMemoryExecutionStateStore implements ExecutionStateStore
{
    /** @var array<string, ExecutionState> */
    private array $states = [];

    public function create(ExecutionState $state): void
    {
        if (isset($this->states[$state->runId->value])) {
            throw new InvalidArgumentException('Execution state already exists.');
        }
        $this->states[$state->runId->value] = $state;
    }

    public function find(RunId $runId): ?ExecutionState
    {
        return $this->states[$runId->value] ?? null;
    }

    public function compareAndSwap(RunId $runId, int $expectedVersion, ExecutionState $next): bool
    {
        $current = $this->find($runId);
        if ($current === null || $current->version !== $expectedVersion || $next->version <= $expectedVersion) {
            return false;
        }
        $this->states[$runId->value] = $next;
        return true;
    }
}
