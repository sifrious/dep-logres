<?php

declare(strict_types=1);

namespace Sifrious\Logres;

final readonly class ExecutionTargetEvaluation
{
    public function __construct(
        public ExecutionTargetCandidate $candidate,
        public bool $eligible,
        public array $matchedCapabilities,
        public array $rejectionReasons,
        public array $policyChecks,
        public int $rank,
        public string $rankKey,
    ) {}
}
