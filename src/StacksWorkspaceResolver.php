<?php

declare(strict_types=1);

namespace Sifrious\Logres;

use Sifrious\StacksContract\ExecutionProvenance;
use Sifrious\StacksContract\WorkspaceReference;

/**
 * Host adapter over the canonical Stacks registry. Logres never owns or
 * persists a parallel workspace registry.
 */
interface StacksWorkspaceResolver
{
    /** @return list<WorkspaceReference> */
    public function resolve(string $workspaceReference): array;

    public function captureExecutionProvenance(WorkspaceReference $workspace): ExecutionProvenance;
}
