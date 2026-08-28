<?php

declare(strict_types=1);

namespace Sifrious\Logres;

use InvalidArgumentException;

final readonly class ExecutionTargetAuthorization
{
    public array $targetIds;

    public array $workspaceAuthorities;

    public array $repositoryIdentities;

    public function __construct(
        public string $callerIdentity,
        array $targetIds,
        array $workspaceAuthorities,
        array $repositoryIdentities,
    ) {
        if (trim($callerIdentity) === '') {
            throw new InvalidArgumentException('Target authorization requires a caller identity.');
        }

        $this->targetIds = array_values(array_unique($targetIds));
        $this->workspaceAuthorities = array_values(array_unique($workspaceAuthorities));
        $this->repositoryIdentities = array_values(array_unique($repositoryIdentities));
    }

    public function allows(ExecutionTargetCandidate $candidate, ExecutionTargetRequirements $requirements): bool
    {
        return in_array($candidate->id->value, $this->targetIds, true)
            && in_array($requirements->workspaceAuthority, $this->workspaceAuthorities, true)
            && in_array($requirements->repositoryIdentity, $this->repositoryIdentities, true);
    }
}
