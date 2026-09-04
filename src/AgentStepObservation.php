<?php

declare(strict_types=1);

namespace Sifrious\Logres;

use DateTimeImmutable;
use InvalidArgumentException;

final readonly class AgentStepObservation
{
    /** @param list<EvidenceReference> $evidence */
    public function __construct(
        public AgentStepId $stepId,
        public string $operationIdentity,
        public DateTimeImmutable $observedAt,
        public array $evidence = [],
        public ?string $detail = null,
    ) {
        if (trim($operationIdentity) === '') {
            throw new InvalidArgumentException('Agent Step observation requires an operation identity.');
        }
    }

    public static function noEffect(AgentStepDetermination $determination, DateTimeImmutable $observedAt): self
    {
        return new self(
            $determination->stepId,
            $determination->operationIdentity(),
            $observedAt,
            $determination->evidence,
            'No external effect was required.',
        );
    }
}
