<?php

declare(strict_types=1);

namespace Sifrious\StacksContract;

use InvalidArgumentException;

final readonly class ExecutionProvenance
{
    /** @param array<string, mixed> $metadata */
    public function __construct(
        public string $workspaceId,
        public string $repositoryId,
        public string $remoteIdentity,
        public string $checkoutId,
        public string $checkoutType,
        public string $executionPath,
        public string $startingRevision,
        public string $branch,
        public string $repositoryCloneUrl,
        public string $capturedAt,
        public array $metadata = [],
    ) {
        foreach ([
            'workspaceId' => $workspaceId,
            'repositoryId' => $repositoryId,
            'remoteIdentity' => $remoteIdentity,
            'checkoutId' => $checkoutId,
            'checkoutType' => $checkoutType,
            'executionPath' => $executionPath,
            'startingRevision' => $startingRevision,
            'branch' => $branch,
            'repositoryCloneUrl' => $repositoryCloneUrl,
            'capturedAt' => $capturedAt,
        ] as $field => $value) {
            if (trim($value) === '') {
                throw new InvalidArgumentException("Execution provenance {$field} is required.");
            }
        }
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'schema' => 'stacks.execution-provenance.v1',
            'workspace_id' => $this->workspaceId,
            'repository_id' => $this->repositoryId,
            'remote_identity' => $this->remoteIdentity,
            'checkout_id' => $this->checkoutId,
            'checkout_type' => $this->checkoutType,
            'execution_path' => $this->executionPath,
            'starting_revision' => $this->startingRevision,
            'branch' => $this->branch,
            'repository_clone_url' => $this->repositoryCloneUrl,
            'captured_at' => $this->capturedAt,
            'metadata' => $this->metadata,
        ];
    }
}
