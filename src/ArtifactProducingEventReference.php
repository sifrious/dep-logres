<?php

declare(strict_types=1);

namespace Sifrious\Logres;

use InvalidArgumentException;

final readonly class ArtifactProducingEventReference
{
    public function __construct(
        public RunId $runId,
        public string $providerExecutionId,
        public string $providerEventId,
        public int $sequence,
        public string $stableIdentity,
        public string $normalizedType,
    ) {
        if (trim($this->providerExecutionId) === '' || trim($this->providerEventId) === '' || trim($this->stableIdentity) === '' || trim($this->normalizedType) === '' || $this->sequence < 1) {
            throw new InvalidArgumentException('Artifact-producing event references require provider identity, sequence, and normalized type.');
        }
    }

    public function toArray(): array
    {
        return [
            'kind' => 'artifact_producing_event',
            'run_id' => $this->runId->value,
            'provider_execution_id' => $this->providerExecutionId,
            'provider_event_id' => $this->providerEventId,
            'sequence' => $this->sequence,
            'stable_identity' => $this->stableIdentity,
            'normalized_type' => $this->normalizedType,
        ];
    }
}
