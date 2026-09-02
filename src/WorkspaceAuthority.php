<?php

declare(strict_types=1);

namespace Sifrious\Logres;

use InvalidArgumentException;

final readonly class WorkspaceAuthority
{
    public function __construct(public string $value)
    {
        if (preg_match('/^(?:workspace:[a-zA-Z0-9._:-]+|ws_[a-zA-Z0-9]+)$/', $value) !== 1) {
            throw new InvalidArgumentException('Workspace authority must be a legacy workspace: value or canonical Stacks workspace ID.');
        }
    }
}
