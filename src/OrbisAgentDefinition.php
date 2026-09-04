<?php

declare(strict_types=1);

namespace Sifrious\Logres;

use InvalidArgumentException;

final readonly class OrbisAgentDefinition
{
    public function __construct(
        public string $id,
        public string $version,
        public string $contentHash,
    ) {
        if (preg_match('/^agent:[a-zA-Z0-9._-]+$/', $id) !== 1
            || trim($version) === ''
            || preg_match('/^[a-f0-9]{64}$/', $contentHash) !== 1) {
            throw new InvalidArgumentException('An Orbis agent definition requires stable identity, version, and SHA-256 content hash.');
        }
    }
}
