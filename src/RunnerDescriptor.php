<?php

declare(strict_types=1);

namespace Sifrious\Logres;

final readonly class RunnerDescriptor
{
    /**
     * @param list<string> $authorizationGrantReferences
     * @param list<string> $workspaceIdentities
     */
    public function __construct(
        public RunnerIdentity $identity,
        public PlatformIdentity $platform,
        public CapabilitySnapshot $capabilities,
        public RunnerAvailability $availability,
        public CurrentWorkload $workload,
        public array $authorizationGrantReferences = [],
        public array $workspaceIdentities = [],
    ) {}

    public function compatibleWith(RunnerCompatibilityRequirements $requirements): RunnerCompatibility
    {
        $compatibility = $this->capabilities->supports($requirements);
        $failures = $compatibility->failures;
        if (! in_array($requirements->workspaceIdentity, $this->workspaceIdentities, true)) {
            $failures[] = RunnerCompatibilityFailure::WorkspaceIdentity;
        }
        if (! in_array($requirements->authorizationGrantReference, $this->authorizationGrantReferences, true)) {
            $failures[] = RunnerCompatibilityFailure::AuthorizationGrant;
        }

        return new RunnerCompatibility($failures);
    }
}
