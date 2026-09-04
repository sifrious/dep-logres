<?php

declare(strict_types=1);

namespace Sifrious\Logres;

use InvalidArgumentException;

final readonly class ArtifactReference implements ExecutionEventReference
{
    public function __construct(
        public string $id,
        public RunId $runId,
        public string $type,
        public string $locator,
        public string $mediaType,
        public int $size,
        public string $integrity,
        public ArtifactAccessClassification $accessClassification = ArtifactAccessClassification::Internal,
        public string $retention = 'run-retained',
        public ?string $derivedFromArtifactId = null,
        public ?string $supersedesArtifactId = null,
    ) {
        if (trim($this->id) === '' || trim($this->type) === '' || trim($this->locator) === '' || trim($this->mediaType) === '' || trim($this->integrity) === '') {
            throw new InvalidArgumentException('Artifact identity, type, run ownership, locator, media type, and integrity are required.');
        }

        if ($this->size < 0) {
            throw new InvalidArgumentException('Artifact size cannot be negative.');
        }

        if (trim($this->retention) === '') {
            throw new InvalidArgumentException('Artifact retention metadata is required.');
        }

        if ($this->derivedFromArtifactId !== null && trim($this->derivedFromArtifactId) === '') {
            throw new InvalidArgumentException('Artifact derivation references cannot be empty.');
        }

        if ($this->supersedesArtifactId !== null && trim($this->supersedesArtifactId) === '') {
            throw new InvalidArgumentException('Artifact supersession references cannot be empty.');
        }

        if ($this->supersedesArtifactId !== null && $this->supersedesArtifactId === $this->id) {
            throw new InvalidArgumentException('An artifact cannot supersede itself.');
        }
    }

    public function toArray(): array
    {
        return [
            'kind' => 'artifact',
            'id' => $this->id,
            'run_id' => $this->runId->value,
            'artifact_type' => $this->type,
            'locator' => $this->locator,
            'media_type' => $this->mediaType,
            'size' => $this->size,
            'integrity' => $this->integrity,
            'access_classification' => $this->accessClassification->value,
            'retention' => $this->retention,
            'derived_from_artifact_id' => $this->derivedFromArtifactId,
            'supersedes_artifact_id' => $this->supersedesArtifactId,
        ];
    }

    public function toPublicArray(): array
    {
        $public = $this->toArray();
        if (in_array($this->accessClassification, [ArtifactAccessClassification::Restricted, ArtifactAccessClassification::Secret], true)) {
            $public['locator'] = '[REDACTED]';
            $public['integrity'] = '[REDACTED]';
            $public['sensitive'] = true;
        }

        return $public;
    }
}
