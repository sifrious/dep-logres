<?php

declare(strict_types=1);

namespace Sifrious\Logres;

use DateTimeImmutable;
use InvalidArgumentException;

final readonly class DispatchAuthorizationSnapshot
{
    public array $permissions;

    public function __construct(
        public string $grantId,
        public string $actor,
        public ExecutionTargetId $targetId,
        public RepositoryIdentity $repositoryIdentity,
        public WorkspaceAuthority $workspaceAuthority,
        public WorkspacePath $workspacePath,
        public string $environment,
        public string $runtime,
        array $permissions,
        public string $policyVersion,
        public string $grantIssuedAt,
        public string $grantExpiresAt,
        public string $authorizedAt,
    ) {
        if (preg_match('/^grant:[a-zA-Z0-9._-]+$/', $grantId) !== 1
            || $permissions === []
            || ! self::permissions($permissions)
            || trim($environment) === ''
            || trim($runtime) === ''
            || trim($actor) === ''
            || trim($policyVersion) === ''
            || $workspacePath->value === '/'
            || ! self::timestamp($grantIssuedAt)
            || ! self::timestamp($grantExpiresAt)
            || ! self::timestamp($authorizedAt)
            || new DateTimeImmutable($authorizedAt) < new DateTimeImmutable($grantIssuedAt)
            || new DateTimeImmutable($authorizedAt) >= new DateTimeImmutable($grantExpiresAt)) {
            throw new InvalidArgumentException('A dispatch authorization snapshot must preserve complete approved authority.');
        }

        $permissions = array_values(array_unique($permissions));
        sort($permissions);
        $this->permissions = $permissions;
    }

    private static function permissions(array $permissions): bool
    {
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
