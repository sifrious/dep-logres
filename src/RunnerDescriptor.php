<?php

declare(strict_types=1);

namespace Sifrious\Logres;

final readonly class RunnerDescriptor
{
    /** @param list<string> $authorizationGrantReferences */
    public function __construct(
        public RunnerIdentity $identity,
        public PlatformIdentity $platform,
        public CapabilitySnapshot $capabilities,
        public RunnerAvailability $availability,
        public CurrentWorkload $workload,
        public array $authorizationGrantReferences = [],
    ) {}
}
