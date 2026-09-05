<?php

declare(strict_types=1);

namespace Sifrious\Logres;

final readonly class LoopBudgetRemaining
{
    public function __construct(
        public int $steps,
        public int $attempts,
        public int $wallClockSeconds,
        public int $toolCalls,
        public int $consecutiveFailures,
        public ?int $tokens,
        public ?int $costMicros,
        public int $delegationDepth,
        public int $concurrentChildren,
        public ?int $inputWaitSeconds,
    ) {
    }

    /** @return array<string, int|null> */
    public function toArray(): array
    {
        return [
            'steps' => $this->steps,
            'attempts' => $this->attempts,
            'wall_clock_seconds' => $this->wallClockSeconds,
            'tool_calls' => $this->toolCalls,
            'consecutive_failures' => $this->consecutiveFailures,
            'tokens' => $this->tokens,
            'cost_micros' => $this->costMicros,
            'delegation_depth' => $this->delegationDepth,
            'concurrent_children' => $this->concurrentChildren,
            'input_wait_seconds' => $this->inputWaitSeconds,
        ];
    }
}
