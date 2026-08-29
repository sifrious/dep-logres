<?php

declare(strict_types=1);

namespace Sifrious\Logres;

interface RunnerWorkspace
{
    public function isAvailable(WorkspaceAuthority $identity): bool;
    public function matches(WorkspaceAuthority $identity, WorkspacePath $path, RepositoryIdentity $repository): bool;
}
