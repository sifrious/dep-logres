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
    ) {
        if (trim($provider) === '' || trim($workspaceAuthority) === '' || trim($repositoryIdentity) === '' || trim($agentAdapter) === '' || ! self::hasCapabilities($capabilities)) {
            throw new InvalidArgumentException('Target requirements need provider, workspace authority, repository identity, agent adapter, and capabilities.');
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
