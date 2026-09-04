<?php

declare(strict_types=1);

namespace Sifrious\Logres;

use InvalidArgumentException;

final readonly class DelegationContext
{
    /** @var list<string> */
    public array $permissions;

    /** @var array<string, mixed> */
    public array $payload;

    /**
     * @param list<string> $permissions
     * @param array<string, mixed> $payload
     */
    private function __construct(
        public RepositoryIdentity $repositoryIdentity,
        public WorkspaceAuthority $workspaceAuthority,
        public WorkspacePath $workspacePath,
        public string $environment,
        public string $runtime,
        array $permissions,
        array $payload,
    ) {
        $this->permissions = $permissions;
        $this->payload = $payload;
    }

    /**
     * A child may narrow inherited authority, but cannot change or widen it.
     *
     * @param list<string> $permissions
     * @param array<string, mixed> $payload
     */
    public static function boundedBy(
        DispatchAuthorizationSnapshot $parent,
        WorkspacePath $workspacePath,
        array $permissions,
        array $payload,
    ): self {
        if (! $parent->workspacePath->contains($workspacePath)) {
            throw new InvalidArgumentException('A delegated workspace path must remain inside the parent authorization.');
        }

        if ($permissions === [] || array_diff($permissions, $parent->permissions) !== []) {
            throw new InvalidArgumentException('Delegated permissions must be a non-empty subset of parent authority.');
        }

        $permissions = array_values(array_unique($permissions));
        sort($permissions);

        return new self(
            $parent->repositoryIdentity,
            $parent->workspaceAuthority,
            $workspacePath,
            $parent->environment,
            $parent->runtime,
            $permissions,
            $payload,
        );
    }
}
