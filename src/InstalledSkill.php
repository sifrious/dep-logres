<?php

declare(strict_types=1);

namespace Sifrious\Logres;

use InvalidArgumentException;

final readonly class InstalledSkill
{
    public function __construct(
        public SkillManifest $manifest,
        public string $location,
        public string $contentHash,
    ) {
        if (trim($this->location) === '' || trim($this->contentHash) === '') {
            throw new InvalidArgumentException('An installed skill requires a location and content hash.');
        }
    }

    public function canonicalLocation(): string
    {
        return realpath($this->location) ?: $this->location;
    }
}
