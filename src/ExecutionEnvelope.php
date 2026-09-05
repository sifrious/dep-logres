<?php

declare(strict_types=1);

namespace Sifrious\Logres;

use DateTimeImmutable;
use InvalidArgumentException;
use Sifrious\StacksContract\ExecutionProvenance;
use Sifrious\StacksContract\WorkspaceReference;

final readonly class ExecutionEnvelope
{
    /** @var list<string> */ public array $requiredCapabilities;
    /** @var array<string, mixed> */ public array $requestPayload;

    public function __construct(
        public RunId $runId,
        public AttemptId $attemptId,
        public LeaseId $leaseId,
        public LeaseToken $leaseToken,
        public RunnerIdentity $targetRunnerId,
        public WorkspaceAuthority $workspaceIdentity,
        public WorkspacePath $workspacePath,
        public RepositoryIdentity $repositoryIdentity,
        public string $runtime,
        public string $runtimeAdapter,
        public string $authorizationGrantReference,
        public DateTimeImmutable $issuedAt,
        public DateTimeImmutable $expiresAt,
        public string $protocolVersion,
        public string $idempotencyIdentity,
        public string $authenticationMaterial,
        array $requiredCapabilities,
        array $requestPayload,
        public ?StacksExecutionContext $stacksContext = null,
    ) {
        if ($expiresAt <= $issuedAt || trim($runtime) === '' || trim($runtimeAdapter) === '' || trim($authorizationGrantReference) === '' || trim($protocolVersion) === '' || trim($idempotencyIdentity) === '' || trim($authenticationMaterial) === '') {
            throw new InvalidArgumentException('An execution envelope requires bounded validity and complete immutable execution authority.');
        }
        foreach ($requiredCapabilities as $capability) {
            if (! is_string($capability) || trim($capability) === '') {
                throw new InvalidArgumentException('Required capabilities must be non-empty strings.');
            }
        }
        $this->requiredCapabilities = array_values(array_unique($requiredCapabilities));
        $this->requestPayload = $requestPayload;
        if ($stacksContext !== null && (
            $workspaceIdentity->value !== $stacksContext->workspace->workspaceId
            || $workspacePath->value !== $stacksContext->provenance->executionPath
            || $repositoryIdentity->value !== $stacksContext->workspace->repositoryId
            || ! $stacksContext->isDispatchable()
            || $stacksContext->resultingRevision !== null
        )) {
            throw new InvalidArgumentException('Legacy execution fields must match the canonical Stacks context during migration.');
        }
    }

    /** @param array<string, mixed> $input */
    public static function parse(array $input): self
    {
        $required = ['run_id', 'attempt_id', 'lease_id', 'lease_token', 'target_runner_id', 'workspace_identity', 'workspace_path', 'repository_identity', 'runtime', 'runtime_adapter', 'authorization_grant_reference', 'issued_at', 'expires_at', 'protocol_version', 'idempotency_identity', 'authentication_material', 'required_capabilities', 'request_payload'];
        foreach ($required as $key) {
            if (! array_key_exists($key, $input)) {
                throw new InvalidArgumentException("Execution envelope is missing {$key}.");
            }
        }
        foreach (array_diff($required, ['required_capabilities', 'request_payload']) as $key) {
            if (! is_string($input[$key])) {
                throw new InvalidArgumentException("Execution envelope field {$key} must be a string.");
            }
        }
        if (! is_array($input['required_capabilities']) || ! is_array($input['request_payload'])) {
            throw new InvalidArgumentException('Execution envelope capabilities and payload must be arrays.');
        }

        $stacksContext = self::parseStacksContext($input['stacks_context'] ?? null);

        return new self(
            new RunId($input['run_id']), new AttemptId($input['attempt_id']), new LeaseId($input['lease_id']), new LeaseToken($input['lease_token']),
            new RunnerIdentity($input['target_runner_id']), new WorkspaceAuthority($input['workspace_identity']), new WorkspacePath($input['workspace_path']),
            new RepositoryIdentity($input['repository_identity']), $input['runtime'], $input['runtime_adapter'], $input['authorization_grant_reference'],
            new DateTimeImmutable($input['issued_at']), new DateTimeImmutable($input['expires_at']), $input['protocol_version'], $input['idempotency_identity'],
            $input['authentication_material'], $input['required_capabilities'], $input['request_payload'],
            $stacksContext,
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'run_id' => $this->runId->value,
            'attempt_id' => $this->attemptId->value,
            'lease_id' => $this->leaseId->value,
            'lease_token' => $this->leaseToken->value,
            'target_runner_id' => $this->targetRunnerId->value,
            'workspace_identity' => $this->workspaceIdentity->value,
            'workspace_path' => $this->workspacePath->value,
            'repository_identity' => $this->repositoryIdentity->value,
            'runtime' => $this->runtime,
            'runtime_adapter' => $this->runtimeAdapter,
            'authorization_grant_reference' => $this->authorizationGrantReference,
            'issued_at' => $this->issuedAt->format('Y-m-d\TH:i:s.uP'),
            'expires_at' => $this->expiresAt->format('Y-m-d\TH:i:s.uP'),
            'protocol_version' => $this->protocolVersion,
            'idempotency_identity' => $this->idempotencyIdentity,
            'authentication_material' => $this->authenticationMaterial,
            'required_capabilities' => $this->requiredCapabilities,
            'request_payload' => $this->requestPayload,
            'stacks_context' => $this->stacksContext?->toArray()
                ?? ExecutionProvenanceClassification::missingRecord(),
        ];
    }

    private static function parseStacksContext(mixed $input): ?StacksExecutionContext
    {
        if ($input === null) {
            return null;
        }
        if (is_array($input) && ($input['classification'] ?? null) === ExecutionProvenanceClassification::LegacyMissing->value) {
            return null;
        }
        if (! is_array($input)
            || ! is_array($input['workspace_reference'] ?? null)
            || ! is_array($input['execution_provenance'] ?? null)) {
            throw new InvalidArgumentException('Execution envelope Stacks context must contain workspace reference and execution provenance arrays.');
        }

        $workspace = $input['workspace_reference'];
        $provenance = $input['execution_provenance'];
        $workspaceFields = ['workspace_id', 'repository_id', 'remote_identity', 'checkout_id', 'checkout_type', 'availability', 'current_path', 'branch', 'head'];
        $provenanceFields = ['workspace_id', 'repository_id', 'remote_identity', 'checkout_id', 'checkout_type', 'execution_path', 'starting_revision', 'branch', 'repository_clone_url', 'captured_at'];
        foreach ($workspaceFields as $field) {
            if (! is_string($workspace[$field] ?? null)) {
                throw new InvalidArgumentException("Execution envelope Stacks context field {$field} must be a string.");
            }
        }
        foreach ($provenanceFields as $field) {
            if (! is_string($provenance[$field] ?? null)) {
                throw new InvalidArgumentException("Execution envelope Stacks context field {$field} must be a string.");
            }
        }
        if (! is_array($provenance['metadata'] ?? [])) {
            throw new InvalidArgumentException('Execution envelope Stacks provenance metadata must be an array.');
        }

        return new StacksExecutionContext(
            new WorkspaceReference(
                $workspace['workspace_id'], $workspace['repository_id'], $workspace['remote_identity'],
                $workspace['checkout_id'], $workspace['checkout_type'], $workspace['availability'],
                $workspace['current_path'], $workspace['branch'], $workspace['head'],
            ),
            new ExecutionProvenance(
                $provenance['workspace_id'], $provenance['repository_id'], $provenance['remote_identity'],
                $provenance['checkout_id'], $provenance['checkout_type'], $provenance['execution_path'],
                $provenance['starting_revision'], $provenance['branch'], $provenance['repository_clone_url'],
                $provenance['captured_at'], $provenance['metadata'] ?? [],
            ),
            self::nullableString($input, 'revision_evidence', 'requested_base_revision'),
            self::nullableString($input, 'revision_evidence', 'worktree_observation'),
            is_string($input['capability_snapshot_version'] ?? null) ? $input['capability_snapshot_version'] : null,
            is_string($input['selected_execution_target'] ?? null) ? $input['selected_execution_target'] : null,
            self::nullableString($input, 'revision_evidence', 'resulting_revision'),
            self::nullableString($input, 'revision_evidence', 'diff_identity'),
        );
    }

    private static function nullableString(array $input, string $group, string $field): ?string
    {
        $value = is_array($input[$group] ?? null) ? ($input[$group][$field] ?? null) : null;

        return is_string($value) ? $value : null;
    }
}
