<?php

declare(strict_types=1);

namespace Sifrious\Logres;

use InvalidArgumentException;
use JsonSerializable;
use Sifrious\StacksContract\ExecutionProvenance;
use Sifrious\StacksContract\WorkspaceReference;

/**
 * Immutable Stacks identity plus the evidence captured before dispatch.
 *
 * Rendering this snapshot never resolves a live workspace or reads Stacks
 * persistence, so historical Runs remain truthful after local state changes.
 */
final readonly class StacksExecutionContext implements JsonSerializable
{
    public function __construct(
        public WorkspaceReference $workspace,
        public ExecutionProvenance $provenance,
    ) {
        if ($workspace->availability !== 'available') {
            throw new InvalidArgumentException('Execution requires an available Stacks workspace reference.');
        }
        if (
            $workspace->workspaceId !== $provenance->workspaceId
            || $workspace->repositoryId !== $provenance->repositoryId
            || $workspace->checkoutId !== $provenance->checkoutId
            || $workspace->currentPath !== $provenance->executionPath
            || $workspace->head !== $provenance->startingRevision
        ) {
            throw new InvalidArgumentException('Stacks workspace reference and captured execution provenance must describe the same dispatch observation.');
        }
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'workspace_reference' => $this->workspace->toArray(),
            'execution_provenance' => $this->provenance->toArray(),
        ];
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
