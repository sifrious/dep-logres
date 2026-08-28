<?php

declare(strict_types=1);

namespace Sifrious\Logres;

final readonly class TaskPlanningResult
{
    public array $failures;

    public function __construct(
        public TaskPlanningStatus $status,
        public ?TaskPlan $plan,
        array $failures = [],
    ) {
        $this->failures = array_values($failures);
    }

    public function acceptedSuccessfully(): bool
    {
        return $this->status === TaskPlanningStatus::Accepted && $this->plan !== null;
    }
}
