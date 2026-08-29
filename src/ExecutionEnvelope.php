<?php

declare(strict_types=1);

namespace Sifrious\Logres;

use DateTimeImmutable;
use InvalidArgumentException;

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

        return new self(
            new RunId($input['run_id']), new AttemptId($input['attempt_id']), new LeaseId($input['lease_id']), new LeaseToken($input['lease_token']),
            new RunnerIdentity($input['target_runner_id']), new WorkspaceAuthority($input['workspace_identity']), new WorkspacePath($input['workspace_path']),
            new RepositoryIdentity($input['repository_identity']), $input['runtime'], $input['runtime_adapter'], $input['authorization_grant_reference'],
            new DateTimeImmutable($input['issued_at']), new DateTimeImmutable($input['expires_at']), $input['protocol_version'], $input['idempotency_identity'],
            $input['authentication_material'], $input['required_capabilities'], $input['request_payload'],
        );
    }
}
