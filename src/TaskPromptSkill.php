<?php

declare(strict_types=1);

namespace Sifrious\Logres;

use InvalidArgumentException;

final readonly class TaskPromptSkill
{
    public function __construct(
        public string $id,
        public string $version,
        public string $contentHash,
    ) {
        if (trim($id) === '' || trim($version) === '' || preg_match('/^[a-f0-9]{64}$/', $contentHash) !== 1) {
            throw new InvalidArgumentException('A selected skill requires an identity, version, and SHA-256 content hash.');
        }
    }

    public function canonicalData(): array
    {
        return ['content_hash' => $this->contentHash, 'id' => $this->id, 'version' => $this->version];
    }
}
