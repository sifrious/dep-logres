<?php

declare(strict_types=1);

namespace Sifrious\Logres;

final readonly class AgentStepCycleResult
{
    public function __construct(
        public ?AgentStepRecord $record,
        public bool $reentryScheduled,
        public ?AgentStepId $nextStepId = null,
        public bool $concurrencyLost = false,
    ) {}
}
