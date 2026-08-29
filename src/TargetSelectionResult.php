<?php

declare(strict_types=1);

namespace Sifrious\Logres;

use InvalidArgumentException;

final readonly class TargetSelectionResult
{
    public array $failures;

    public function __construct(
        public TargetSelectionStatus $status,
        public ?ExecutionTargetSelection $selection,
        array $failures = [],
    ) {
        $this->failures = array_values($failures);

        if (($status === TargetSelectionStatus::Selected && ($selection === null || $this->failures !== []))
            || (in_array($status, [TargetSelectionStatus::Rejected, TargetSelectionStatus::NeedsTarget], true) && ($selection !== null || $this->failures === []))) {
            throw new InvalidArgumentException('Target selection status, selection, and failures must agree.');
        }
    }

    public function selectedSuccessfully(): bool
    {
        return $this->status === TargetSelectionStatus::Selected && $this->selection !== null;
    }
}
