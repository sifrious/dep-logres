<?php

declare(strict_types=1);

namespace Sifrious\Logres;

use InvalidArgumentException;
use Sifrious\ReferenceContract\CrossPackageReference;

final readonly class AgentDefinitionRef
{
    public string $id;

    public string $version;

    public function __construct(
        public CrossPackageReference $reference,
        public string $contentHash,
    ) {
        if ($reference->owner !== 'sifrious/orbis'
            || $reference->type !== 'agent-definition'
            || $reference->objectVersion === null
            || preg_match('/^[a-f0-9]{64}$/', $contentHash) !== 1) {
            throw new InvalidArgumentException('An agent definition must be a versioned Orbis cross-package reference with a SHA-256 content hash.');
        }

        $this->id = $reference->id;
        $this->version = $reference->objectVersion;
    }
}
