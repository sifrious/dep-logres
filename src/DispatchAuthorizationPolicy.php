<?php

declare(strict_types=1);

namespace Sifrious\Logres;

use DateTimeImmutable;
use InvalidArgumentException;

final readonly class DispatchAuthorizationPolicy
{
    public function __construct(public int $maximumTargetAgeSeconds = 300)
    {
        if ($maximumTargetAgeSeconds < 1) {
            throw new InvalidArgumentException('Target freshness must be a positive number of seconds.');
        }
    }

    public function authorize(
        Run $run,
        ExecutionGrant $grant,
        ?WorkspacePath $requestedPath,
        array $observedRepositoryIdentities,
        string $environment,
        string $authorizedAt,
    ): DispatchAuthorizationDecision {
        $failures = [];
        $target = $run->provenance->targetSelection->target;
        $repository = new RepositoryIdentity($target->repositoryIdentity);
        $workspace = new WorkspaceAuthority($target->workspaceAuthority);
        $authorizedTime = $this->time($authorizedAt, 'authorization_time_invalid', $failures);
        $observedRepository = $this->observedRepository($observedRepositoryIdentities, $failures);

        if ($run->providerBindingStatus !== ProviderBindingStatus::NotDispatched) {
            $failures[] = new DispatchAuthorizationFailure('run_already_dispatched', 'Only a not-dispatched Run can receive dispatch authorization.');
        }

        if ($run->dispatchAuthorization !== null) {
            $failures[] = new DispatchAuthorizationFailure('run_already_authorized', 'Dispatch authorization is immutable once frozen on a Run.');
        }

        if ($grant->actor !== $run->provenance->initiatingActor) {
            $failures[] = new DispatchAuthorizationFailure('actor_unauthorized', 'The grant actor does not match the Run initiator.');
        }

        if ($grant->targetId->value !== $target->id->value) {
            $failures[] = new DispatchAuthorizationFailure('target_unauthorized', 'The grant does not authorize the selected target.');
        }

        if ($grant->repositoryIdentity->value !== $repository->value || $observedRepository?->value !== $repository->value) {
            $failures[] = new DispatchAuthorizationFailure('repository_mismatch', 'The selected, granted, and observed repository identities must match.');
        }

        if ($grant->workspaceAuthority->value !== $workspace->value) {
            $failures[] = new DispatchAuthorizationFailure('workspace_unauthorized', 'The grant does not authorize the selected workspace authority.');
        }

        if ($requestedPath === null) {
            $failures[] = new DispatchAuthorizationFailure('workspace_path_missing', 'Dispatch requires an explicit normalized workspace path.');
        } elseif (! $grant->workspaceRoot->contains($requestedPath)) {
            $failures[] = new DispatchAuthorizationFailure('workspace_path_escape', 'The requested path escapes the granted workspace root.');
        }

        if (trim($environment) === '' || $environment !== $target->environment || $environment !== $grant->environment) {
            $failures[] = new DispatchAuthorizationFailure('environment_mismatch', 'The requested, selected, and granted environments must match.');
        }

        if ($grant->runtime !== $target->runtime) {
            $failures[] = new DispatchAuthorizationFailure('runtime_mismatch', 'The grant does not authorize the selected runtime.');
        }

        if (array_diff($run->provenance->requestedPermissions, $grant->permissions) !== []) {
            $failures[] = new DispatchAuthorizationFailure('permissions_missing', 'The grant does not contain every permission requested by the frozen prompt.');
        }

        if ($authorizedTime !== null) {
            $issuedAt = new DateTimeImmutable($grant->issuedAt);
            $expiresAt = new DateTimeImmutable($grant->expiresAt);

            if ($authorizedTime < $issuedAt || $authorizedTime >= $expiresAt) {
                $failures[] = new DispatchAuthorizationFailure('grant_stale', 'The execution grant is not active at authorization time.');
            }

            $observedAt = new DateTimeImmutable($target->observedAt);
            $age = $authorizedTime->getTimestamp() - $observedAt->getTimestamp();

            if ($age < 0 || $age > $this->maximumTargetAgeSeconds) {
                $failures[] = new DispatchAuthorizationFailure('target_snapshot_stale', 'The target observation is outside the permitted freshness window.');
            }
        }

        if ($failures !== []) {
            return new DispatchAuthorizationDecision(false, null, $failures);
        }

        return new DispatchAuthorizationDecision(
            true,
            new DispatchAuthorizationSnapshot(
                grantId: $grant->id,
                actor: $grant->actor,
                targetId: $grant->targetId,
                repositoryIdentity: $grant->repositoryIdentity,
                workspaceAuthority: $grant->workspaceAuthority,
                workspacePath: $requestedPath,
                environment: $environment,
                runtime: $grant->runtime,
                permissions: $run->provenance->requestedPermissions,
                policyVersion: $grant->policyVersion,
                grantIssuedAt: $grant->issuedAt,
                grantExpiresAt: $grant->expiresAt,
                authorizedAt: $authorizedAt,
            ),
            [],
        );
    }

    private function observedRepository(array $identities, array &$failures): ?RepositoryIdentity
    {
        if (count($identities) !== 1 || ! $identities[0] instanceof RepositoryIdentity) {
            $failures[] = new DispatchAuthorizationFailure(
                $identities === [] ? 'repository_missing' : 'repository_ambiguous',
                'Dispatch requires exactly one observed canonical repository identity.',
            );

            return null;
        }

        return $identities[0];
    }

    private function time(string $value, string $code, array &$failures): ?DateTimeImmutable
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d+)?Z$/', $value) !== 1) {
            $failures[] = new DispatchAuthorizationFailure($code, 'Dispatch authorization requires an explicit UTC timestamp.');

            return null;
        }

        return new DateTimeImmutable($value);
    }
}
