<?php

declare(strict_types=1);

namespace Sifrious\StacksContract;

use InvalidArgumentException;

final readonly class WorkspaceReference
{
    public function __construct(
        public string $workspaceId,
        public string $repositoryId,
        public string $remoteIdentity,
        public string $checkoutId,
        public string $checkoutType,
        public string $availability,
        public string $currentPath,
        public string $branch,
        public string $head,
    ) {
        foreach ([
            'workspaceId' => $workspaceId,
            'repositoryId' => $repositoryId,
            'remoteIdentity' => $remoteIdentity,
            'checkoutId' => $checkoutId,
            'checkoutType' => $checkoutType,
            'availability' => $availability,
            'currentPath' => $currentPath,
            'branch' => $branch,
            'head' => $head,
        ] as $field => $value) {
            if (trim($value) === '') {
                throw new InvalidArgumentException("Workspace reference {$field} is required.");
            }
        }
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'schema' => 'stacks.workspace-reference.v1',
            'workspace_id' => $this->workspaceId,
            'repository_id' => $this->repositoryId,
            'remote_identity' => $this->remoteIdentity,
            'checkout_id' => $this->checkoutId,
            'checkout_type' => $this->checkoutType,
            'availability' => $this->availability,
            'current_path' => $this->currentPath,
            'branch' => $this->branch,
            'head' => $this->head,
        ];
    }
}
