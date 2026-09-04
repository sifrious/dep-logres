<?php

declare(strict_types=1);

namespace Sifrious\Logres;

use DateTimeImmutable;
use InvalidArgumentException;

final readonly class RunArtifactAttachment
{
    public function __construct(
        public ArtifactReference $artifact,
        public ArtifactProducingEventReference $producingEvent,
        public DateTimeImmutable $attachedAt,
        public RunArtifactAttachmentStatus $status = RunArtifactAttachmentStatus::Attached,
        public ?string $observedIntegrity = null,
        public ?string $storageFailure = null,
    ) {
        if ($this->artifact->runId->value !== $this->producingEvent->runId->value) {
            throw new InvalidArgumentException('Artifact ownership must match the producing event Run.');
        }

        if ($this->status === RunArtifactAttachmentStatus::HashMismatch && trim((string) $this->observedIntegrity) === '') {
            throw new InvalidArgumentException('Hash mismatch attachments must include observed integrity evidence.');
        }

        if ($this->status === RunArtifactAttachmentStatus::StorageMissing && trim((string) $this->storageFailure) === '') {
            throw new InvalidArgumentException('Missing-storage attachments must include a recoverable failure reason.');
        }
    }

    public function toArray(): array
    {
        return [
            'artifact' => $this->artifact->toArray(),
            'producing_event' => $this->producingEvent->toArray(),
            'attached_at' => $this->attachedAt->format(DATE_ATOM),
            'status' => $this->status->value,
            'observed_integrity' => $this->observedIntegrity,
            'storage_failure' => $this->storageFailure,
        ];
    }

    public function toPublicArray(): array
    {
        return [
            'artifact' => $this->artifact->toPublicArray(),
            'producing_event' => $this->producingEvent->toArray(),
            'attached_at' => $this->attachedAt->format(DATE_ATOM),
            'status' => $this->status->value,
            'observed_integrity' => $this->status === RunArtifactAttachmentStatus::HashMismatch ? $this->observedIntegrity : null,
            'storage_failure' => $this->storageFailure,
        ];
    }
}
