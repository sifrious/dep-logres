<?php

declare(strict_types=1);

namespace Sifrious\Logres;

use DateTimeImmutable;
use InvalidArgumentException;

final readonly class RunnerCompatibilityRequirements
{
    /** @var list<string> */ public array $capabilities;

    public function __construct(
        public string $runtimeAdapterProfile,
        public string $protocolVersion,
        array $capabilities,
        public string $workspaceIdentity,
        public string $authorizationGrantReference,
        public ?DateTimeImmutable $evaluatedAt = null,
        public int $maximumSnapshotAgeSeconds = 300,
    ) {
        foreach ([$runtimeAdapterProfile, $protocolVersion, $workspaceIdentity, $authorizationGrantReference] as $value) {
            if (trim($value) === '') {
                throw new InvalidArgumentException('Runner compatibility requirements must use stable non-empty identities.');
            }
        }
        if ($capabilities === [] || array_filter($capabilities, static fn ($value): bool => ! is_string($value) || trim($value) === '') !== [] || $maximumSnapshotAgeSeconds < 0) {
            throw new InvalidArgumentException('Runner compatibility requirements need capabilities.');
        }
        $capabilities = array_values(array_unique($capabilities));
        sort($capabilities);
        $this->capabilities = $capabilities;
    }
}
