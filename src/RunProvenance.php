<?php

declare(strict_types=1);

namespace Sifrious\Logres;

use InvalidArgumentException;

final readonly class RunProvenance
{
    public array $policyVersions;

    public array $requestedPermissions;

    public function __construct(
        public ExecutionRequestId $requestId,
        public TaskId $taskId,
        public TaskPromptId $promptId,
        public int $promptVersion,
        public string $promptCompilerVersion,
        public string $promptProvenanceHash,
        array $requestedPermissions,
        public ExecutionTargetSelection $targetSelection,
        array $policyVersions,
        public string $initiatingActor,
        public string $createdAt,
    ) {
        if ($promptVersion < 1
            || $promptId->value !== "prompt:{$taskId->value}:v{$promptVersion}"
            || $targetSelection->taskId->value !== $taskId->value
            || trim($promptCompilerVersion) === ''
            || preg_match('/^[a-f0-9]{64}$/', $promptProvenanceHash) !== 1
            || ! self::permissions($requestedPermissions)
            || ! self::versions($policyVersions)
            || trim($initiatingActor) === ''
            || preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d+)?Z$/', $createdAt) !== 1) {
            throw new InvalidArgumentException('Run provenance requires consistent request, task, prompt, target, policy, actor, and creation identities.');
        }

        ksort($policyVersions);
        $requestedPermissions = array_values(array_unique($requestedPermissions));
        sort($requestedPermissions);
        $this->policyVersions = $policyVersions;
        $this->requestedPermissions = $requestedPermissions;
    }

    public static function capture(
        TaskPrompt $prompt,
        ExecutionTargetSelection $targetSelection,
        array $policyVersions,
        string $initiatingActor,
        string $createdAt,
    ): self {
        return new self(
            requestId: $prompt->requestId,
            taskId: $prompt->taskId,
            promptId: $prompt->id,
            promptVersion: $prompt->version,
            promptCompilerVersion: $prompt->compilerVersion,
            promptProvenanceHash: $prompt->provenanceHash,
            requestedPermissions: $prompt->input->allowedOperations,
            targetSelection: $targetSelection,
            policyVersions: $policyVersions,
            initiatingActor: $initiatingActor,
            createdAt: $createdAt,
        );
    }

    private static function versions(array $versions): bool
    {
        if ($versions === [] || array_is_list($versions)) {
            return false;
        }

        foreach ($versions as $name => $version) {
            if (! is_string($name) || trim($name) === '' || ! is_string($version) || trim($version) === '') {
                return false;
            }
        }

        return true;
    }

    private static function permissions(array $permissions): bool
    {
        if ($permissions === []) {
            return false;
        }

        foreach ($permissions as $permission) {
            if (! is_string($permission) || trim($permission) === '' || $permission === '*' || $permission === 'shell:unrestricted') {
                return false;
            }
        }

        return true;
    }
}
