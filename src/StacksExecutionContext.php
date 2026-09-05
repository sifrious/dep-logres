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
        public ?string $requestedBaseRevision = null,
        public ?string $worktreeObservation = null,
        public ?string $capabilitySnapshotVersion = null,
        public ?string $selectedExecutionTarget = null,
        public ?string $resultingRevision = null,
        public ?string $diffIdentity = null,
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
        if (($resultingRevision === null) !== ($diffIdentity === null)) {
            throw new InvalidArgumentException('Resulting revision and diff identity must be recorded together.');
        }
        foreach ([
            'requested base revision' => $requestedBaseRevision,
            'worktree observation' => $worktreeObservation,
            'capability snapshot version' => $capabilitySnapshotVersion,
            'selected execution target' => $selectedExecutionTarget,
            'resulting revision' => $resultingRevision,
            'diff identity' => $diffIdentity,
        ] as $field => $value) {
            if ($value !== null && trim($value) === '') {
                throw new InvalidArgumentException("Execution identity {$field} cannot be empty.");
            }
        }
    }

    public static function capture(
        WorkspaceReference $workspace,
        ExecutionProvenance $provenance,
        string $requestedBaseRevision,
        string $worktreeObservation,
        string $capabilitySnapshotVersion,
        string $selectedExecutionTarget,
    ): self {
        return new self(
            $workspace,
            $provenance,
            $requestedBaseRevision,
            $worktreeObservation,
            $capabilitySnapshotVersion,
            $selectedExecutionTarget,
        );
    }

    /** @param array<string, mixed> $input */
    public static function fromArray(array $input): ?self
    {
        if (($input['classification'] ?? null) === ExecutionProvenanceClassification::LegacyMissing->value) {
            return null;
        }
        $workspace = $input['workspace_reference'] ?? null;
        $provenance = $input['execution_provenance'] ?? null;
        if (! is_array($workspace) || ! is_array($provenance)) {
            throw new InvalidArgumentException('Execution identity must contain Stacks workspace reference and execution provenance.');
        }
        $requiredWorkspace = ['workspace_id', 'repository_id', 'remote_identity', 'checkout_id', 'checkout_type', 'availability', 'current_path', 'branch', 'head'];
        $requiredProvenance = ['workspace_id', 'repository_id', 'remote_identity', 'checkout_id', 'checkout_type', 'execution_path', 'starting_revision', 'branch', 'repository_clone_url', 'captured_at'];
        foreach ($requiredWorkspace as $field) {
            if (! is_string($workspace[$field] ?? null)) {
                throw new InvalidArgumentException("Execution identity workspace field {$field} must be a string.");
            }
        }
        foreach ($requiredProvenance as $field) {
            if (! is_string($provenance[$field] ?? null)) {
                throw new InvalidArgumentException("Execution identity provenance field {$field} must be a string.");
            }
        }
        $revision = is_array($input['revision_evidence'] ?? null) ? $input['revision_evidence'] : [];
        $nullable = static fn (mixed $value): ?string => is_string($value) ? $value : null;

        return new self(
            new WorkspaceReference(
                $workspace['workspace_id'], $workspace['repository_id'], $workspace['remote_identity'],
                $workspace['checkout_id'], $workspace['checkout_type'], $workspace['availability'],
                $workspace['current_path'], $workspace['branch'], $workspace['head'],
            ),
            new ExecutionProvenance(
                $provenance['workspace_id'], $provenance['repository_id'], $provenance['remote_identity'],
                $provenance['checkout_id'], $provenance['checkout_type'], $provenance['execution_path'],
                $provenance['starting_revision'], $provenance['branch'], $provenance['repository_clone_url'],
                $provenance['captured_at'], is_array($provenance['metadata'] ?? null) ? $provenance['metadata'] : [],
            ),
            $nullable($revision['requested_base_revision'] ?? null),
            $nullable($revision['worktree_observation'] ?? null),
            $nullable($input['capability_snapshot_version'] ?? null),
            $nullable($input['selected_execution_target'] ?? null),
            $nullable($revision['resulting_revision'] ?? null),
            $nullable($revision['diff_identity'] ?? null),
        );
    }

    public function classification(): ExecutionProvenanceClassification
    {
        return $this->requestedBaseRevision === null
            ? ExecutionProvenanceClassification::LegacyStacksV1
            : ExecutionProvenanceClassification::Complete;
    }

    public function isDispatchable(): bool
    {
        return $this->classification() === ExecutionProvenanceClassification::Complete;
    }

    public function workspaceId(): string
    {
        return $this->workspace->workspaceId;
    }

    /**
     * Stable execution identity deliberately excludes filesystem paths.
     * Paths remain available in the evidence payload, never as canonical keys.
     */
    public function canonicalIdentity(): string
    {
        return 'stacks-execution:'.hash('sha256', implode("\0", [
            $this->workspace->workspaceId,
            $this->workspace->repositoryId,
            $this->workspace->checkoutId,
            $this->provenance->startingRevision,
            (string) $this->requestedBaseRevision,
            (string) $this->capabilitySnapshotVersion,
            (string) $this->selectedExecutionTarget,
        ]));
    }

    public function dispatchEvidenceFingerprint(): string
    {
        return hash('sha256', json_encode($this->dispatchEvidence(), JSON_THROW_ON_ERROR));
    }

    public function assertSameDispatchEvidence(self $observation): void
    {
        if (! hash_equals($this->dispatchEvidenceFingerprint(), $observation->dispatchEvidenceFingerprint())) {
            throw new InvalidArgumentException('Revision or execution-target evidence changed after approval; explicit revalidation is required.');
        }
    }

    public function withResult(string $resultingRevision, string $diffIdentity): self
    {
        return new self(
            $this->workspace,
            $this->provenance,
            $this->requestedBaseRevision,
            $this->worktreeObservation,
            $this->capabilitySnapshotVersion,
            $this->selectedExecutionTarget,
            $resultingRevision,
            $diffIdentity,
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'schema' => 'logres.execution-identity.v1',
            'classification' => $this->classification()->value,
            'canonical_identity' => $this->canonicalIdentity(),
            'workspace_id' => $this->workspace->workspaceId,
            'workspace_reference' => $this->workspace->toArray(),
            'execution_provenance' => $this->provenance->toArray(),
            'revision_evidence' => [
                'requested_base_revision' => $this->requestedBaseRevision,
                'observed_starting_revision' => $this->provenance->startingRevision,
                'worktree_observation' => $this->worktreeObservation,
                'resulting_revision' => $this->resultingRevision,
                'diff_identity' => $this->diffIdentity,
            ],
            'capability_snapshot_version' => $this->capabilitySnapshotVersion,
            'selected_execution_target' => $this->selectedExecutionTarget,
        ];
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /** @return array<string, mixed> */
    private function dispatchEvidence(): array
    {
        return [
            'workspace_id' => $this->workspace->workspaceId,
            'repository_id' => $this->workspace->repositoryId,
            'checkout_id' => $this->workspace->checkoutId,
            'checkout_type' => $this->workspace->checkoutType,
            'requested_base_revision' => $this->requestedBaseRevision,
            'observed_starting_revision' => $this->provenance->startingRevision,
            'execution_path_observation' => $this->provenance->executionPath,
            'worktree_observation' => $this->worktreeObservation,
            'capability_snapshot_version' => $this->capabilitySnapshotVersion,
            'selected_execution_target' => $this->selectedExecutionTarget,
        ];
    }
}
