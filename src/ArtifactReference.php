<?php

declare(strict_types=1);

namespace Sifrious\Logres;

use InvalidArgumentException;

final readonly class ArtifactReference
{
    public function __construct(
        public string $id,
        public string $kind,
        public string $path,
        public string $mediaType,
        public int $size,
        public string $hash,
    ) {
        if (trim($this->id) === '' || trim($this->kind) === '' || trim($this->path) === '' || trim($this->mediaType) === '' || trim($this->hash) === '') {
            throw new InvalidArgumentException('Artifact identity, kind, path, media type, and hash are required.');
        }

        if ($this->size < 0) {
            throw new InvalidArgumentException('Artifact size cannot be negative.');
        }
    }
}
