<?php

declare(strict_types=1);

namespace Sifrious\Logres;

interface ExecutionTargetStore
{
    public function save(ExecutionTargetSelection $selection): void;

    public function findForTask(TaskId $taskId): ?ExecutionTargetSelection;
}
