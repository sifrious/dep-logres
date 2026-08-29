<?php

declare(strict_types=1);

namespace Sifrious\Logres;

use InvalidArgumentException;

final readonly class ExecutionTargetRequirements
{
    public array $capabilities;

    public function __construct(
        public TaskId $taskId,
        public string $provider,
        public string $workspaceAuthority,
        public string $repositoryIdentity,
        public string $agentAdapter,
        array $capabilities,
        public array $allowedExecutionClasses = ['local', 'managed-cloud', 'customer-owned', 'provider-hosted'],
        public int $maximumSnapshotAgeSeconds = 300,
        public ?string $preferredTargetId = null,
    ) {
        if (trim($provider) === '' || trim($workspaceAuthority) === '' || trim($repositoryIdentity) === '' || trim($agentAdapter) === '' || ! self::hasCapabilities($capabilities) || $allowedExecutionClasses === [] || $maximumSnapshotAgeSeconds < 0) {
            throw new InvalidArgumentException('Target requirements need provider, workspace authority, repository identity, agent adapter, and capabilities.');
        }
        foreach ($allowedExecutionClasses as $executionClass) {
            if (! is_string($executionClass) || ! in_array($executionClass, ['local', 'managed-cloud', 'customer-owned', 'provider-hosted'], true)) {
                throw new InvalidArgumentException('Allowed execution classes must use canonical values.');
            }
        }

        $normalizedCapabilities = array_values(array_unique($capabilities));
        sort($normalizedCapabilities);
        $this->capabilities = $normalizedCapabilities;
    }

    private static function hasCapabilities(array $capabilities): bool
    {
        if ($capabilities === []) {
            return false;
        }

        foreach ($capabilities as $capability) {
            if (! is_string($capability) || trim($capability) === '') {
                return false;
            }
        }

        return true;
    }
}
