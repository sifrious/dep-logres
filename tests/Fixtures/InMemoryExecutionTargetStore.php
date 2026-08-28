<?php

declare(strict_types=1);

namespace Sifrious\Logres\Tests\Fixtures;

use Sifrious\Logres\ExecutionTargetSelection;
use Sifrious\Logres\ExecutionTargetStore;
use Sifrious\Logres\TaskId;

final class InMemoryExecutionTargetStore implements ExecutionTargetStore
{
    private array $selections = [];

    public function save(ExecutionTargetSelection $selection): void
    {
        $this->selections[$selection->taskId->value] = $selection;
    }

    public function findForTask(TaskId $taskId): ?ExecutionTargetSelection
    {
        return $this->selections[$taskId->value] ?? null;
    }
}
