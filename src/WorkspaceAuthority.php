<?php

declare(strict_types=1);

namespace Sifrious\Logres;

use InvalidArgumentException;

final readonly class WorkspaceAuthority
{
    public function __construct(public string $value)
    {
        if (preg_match('/^workspace:[a-zA-Z0-9._:-]+$/', $value) !== 1) {
            throw new InvalidArgumentException('Workspace authority must use the workspace: namespace.');
        }
    }
}
