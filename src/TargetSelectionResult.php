<?php

declare(strict_types=1);

namespace Sifrious\Logres;

use InvalidArgumentException;

final readonly class TargetSelectionResult
{
    public array $failures;
    public array $candidateEvaluations;

    public function __construct(
        public TargetSelectionStatus $status,
        public ?ExecutionTargetSelection $selection,
        array $failures = [],
        array $candidateEvaluations = [],
    ) {
        $this->failures = array_values($failures);
        $this->candidateEvaluations = array_values($candidateEvaluations);

        if (($status === TargetSelectionStatus::Selected && ($selection === null || $this->failures !== []))
            || ($status === TargetSelectionStatus::Rejected && ($selection !== null || $this->failures === []))) {
            throw new InvalidArgumentException('Target selection status, selection, and failures must agree.');
        }
    }

    public function selectedSuccessfully(): bool
    {
        return $this->status === TargetSelectionStatus::Selected && $this->selection !== null;
    }
}
