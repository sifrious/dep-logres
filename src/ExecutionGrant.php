<?php

declare(strict_types=1);

namespace Sifrious\Logres;

use DateTimeImmutable;
use InvalidArgumentException;

final readonly class ExecutionGrant
{
    public array $permissions;

    public function __construct(
        public string $id,
        public string $actor,
        public ExecutionTargetId $targetId,
        public RepositoryIdentity $repositoryIdentity,
        public WorkspaceAuthority $workspaceAuthority,
        public WorkspacePath $workspaceRoot,
        public string $environment,
        public string $runtime,
        array $permissions,
        public string $policyVersion,
        public string $issuedAt,
        public string $expiresAt,
    ) {
        if (preg_match('/^grant:[a-zA-Z0-9._-]+$/', $id) !== 1
            || trim($actor) === ''
            || trim($environment) === ''
            || trim($runtime) === ''
            || trim($policyVersion) === ''
            || $workspaceRoot->value === '/'
            || ! self::permissions($permissions)
            || ! self::timestamp($issuedAt)
            || ! self::timestamp($expiresAt)
            || new DateTimeImmutable($expiresAt) <= new DateTimeImmutable($issuedAt)) {
            throw new InvalidArgumentException('An execution grant requires explicit bounded authority, permissions, policy, and validity.');
        }

        $permissions = array_values(array_unique($permissions));
        sort($permissions);
        $this->permissions = $permissions;
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

    private static function timestamp(string $value): bool
    {
        return preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d+)?Z$/', $value) === 1;
    }
}
