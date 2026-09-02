<?php

declare(strict_types=1);

namespace Sifrious\Logres;

use InvalidArgumentException;

final readonly class ExecutionTargetCandidate
{
    public array $agentAdapters;

    public array $capabilities;

    public function __construct(
        public ExecutionTargetId $id,
        public string $provider,
        public TargetAvailability $availability,
        public TargetHealth $health,
        public string $runtime,
        public string $environment,
        public string $workspaceAuthority,
        public string $repositoryIdentity,
        array $agentAdapters,
        array $capabilities,
        public ?TaskId $currentTaskId,
        public string $observedAt,
        public string $executionClass = 'provider-hosted',
        public ?string $executionNodeId = null,
        public ?string $providerTargetId = null,
        public ?string $capabilitySnapshotId = null,
        public ?string $workspaceGrantId = null,
        public ?string $workspaceIdentity = null,
        public int $availableSlots = 1,
        public ?CapabilitySnapshot $capabilitySnapshot = null,
    ) {
        if (trim($provider) === '' || ! str_starts_with($id->value, "target:{$provider}:") || trim($runtime) === '' || trim($environment) === '' || trim($workspaceAuthority) === '' || trim($repositoryIdentity) === '' || ! self::nonemptyStrings($agentAdapters) || ! self::nonemptyStrings($capabilities) || ! self::timestamp($observedAt) || ! in_array($executionClass, ['local', 'managed-cloud', 'customer-owned', 'provider-hosted'], true) || $availableSlots < 0) {
            throw new InvalidArgumentException('A target candidate requires provider facts, runtime, authority, repository, adapters, capabilities, and observation time.');
        }

        $normalizedAdapters = array_values(array_unique($agentAdapters));
        $normalizedCapabilities = array_values(array_unique($capabilities));
        sort($normalizedAdapters);
        sort($normalizedCapabilities);
        $this->agentAdapters = $normalizedAdapters;
        $this->capabilities = $normalizedCapabilities;
    }

    private static function timestamp(string $value): bool
    {
        return preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d+)?Z$/', $value) === 1;
    }

    private static function nonemptyStrings(array $values): bool
    {
        if ($values === []) {
            return false;
        }

        foreach ($values as $value) {
            if (! is_string($value) || trim($value) === '') {
                return false;
            }
        }

        return true;
    }
}
