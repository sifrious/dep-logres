<?php

declare(strict_types=1);

namespace Sifrious\Logres;

use InvalidArgumentException;

final readonly class AgentStepRecord
{
    public function __construct(
        public AgentStepDetermination $determination,
        public ?AgentStepObservation $observation = null,
    ) {
        if ($observation !== null
            && ($observation->stepId->value !== $determination->stepId->value
                || ! hash_equals($observation->operationIdentity, $determination->operationIdentity()))) {
            throw new InvalidArgumentException('Agent Step observation must match its persisted determination.');
        }
    }

    public function isRecorded(): bool
    {
        return $this->observation !== null;
    }
}
