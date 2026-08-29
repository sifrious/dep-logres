<?php

declare(strict_types=1);

namespace Sifrious\Logres\Tests\Fixtures;

use LogicException;

use Sifrious\Logres\ExecutionTargetSelection;
use Sifrious\Logres\ExecutionTargetStore;
use Sifrious\Logres\TaskId;

final class InMemoryExecutionTargetStore implements ExecutionTargetStore
{
    private array $selections = [];

    public function save(ExecutionTargetSelection $selection): void
    {
        if (isset($this->selections[$selection->taskId->value])) {
            throw new LogicException('A persisted execution target selection is immutable; create a new task/run version.');
        }
        $this->selections[$selection->taskId->value] = $selection;
    }

    public function findForTask(TaskId $taskId): ?ExecutionTargetSelection
    {
        return $this->selections[$taskId->value] ?? null;
    }
}
