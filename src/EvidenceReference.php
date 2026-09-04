<?php

declare(strict_types=1);

namespace Sifrious\Logres;

use InvalidArgumentException;

final readonly class EvidenceReference
{
    /** @param array<string, mixed> $metadata */
    public function __construct(
        public string $kind,
        public string $locator,
        public string $observedAt,
        public int $sequence,
        public array $metadata = [],
    ) {
        if (
            trim($this->kind) === ''
            || trim($this->locator) === ''
            || preg_match('/^\\d{4}-\\d{2}-\\d{2}T/', $this->observedAt) !== 1
            || $this->sequence < 1
        ) {
            throw new InvalidArgumentException('Evidence references require kind, locator, timestamp, and positive sequence.');
        }
    }

    public function toRunEvidence(): RunEvidence
    {
        return new RunEvidence(
            kind: 'verification.evidence',
            reference: "{$this->kind}:{$this->locator}",
            observedAt: $this->observedAt,
        );
    }
}
