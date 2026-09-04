<?php

declare(strict_types=1);

namespace Sifrious\Logres;

use InvalidArgumentException;

final readonly class ExecutionIdentityResolver
{
    public function __construct(private StacksWorkspaceResolver $stacks) {}

    public function resolve(
        string $workspaceReference,
        string $authorizedRepositoryId,
        string $selectedPath,
        string $requestedBaseRevision,
        string $worktreeObservation,
        string $capabilitySnapshotVersion,
        string $selectedExecutionTarget,
    ): StacksExecutionContext {
        $matches = $this->stacks->resolve($workspaceReference);
        if (count($matches) !== 1) {
            throw new InvalidArgumentException(
                $matches === []
                    ? 'The Stacks workspace is unknown.'
                    : 'The Stacks workspace reference is ambiguous.',
            );
        }

        $workspace = $matches[0];
        if ($workspace->repositoryId !== $authorizedRepositoryId) {
            throw new InvalidArgumentException('The Stacks workspace repository differs from the authorized repository.');
        }

        $selectedRealPath = realpath($selectedPath);
        $workspaceRealPath = realpath($workspace->currentPath);
        if ($selectedRealPath === false || $workspaceRealPath === false || $selectedRealPath !== $workspaceRealPath) {
            throw new InvalidArgumentException('The execution path does not resolve to the selected Stacks workspace.');
        }

        $provenance = $this->stacks->captureExecutionProvenance($workspace);

        return StacksExecutionContext::capture(
            $workspace,
            $provenance,
            $requestedBaseRevision,
            $worktreeObservation,
            $capabilitySnapshotVersion,
            $selectedExecutionTarget,
        );
    }
}
