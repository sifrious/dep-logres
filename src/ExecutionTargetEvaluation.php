<?php

declare(strict_types=1);

namespace Sifrious\Logres;

use InvalidArgumentException;

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
    ) {
        if ($rank < 0 || trim($rankKey) === '') {
            throw new InvalidArgumentException('Target evaluation requires a non-negative rank and deterministic rank key.');
        }
        if ($eligible !== ($rejectionReasons === [])) {
            throw new InvalidArgumentException('Target eligibility and rejection reasons must agree.');
        }
    }

    public function canonicalData(): array
    {
        return [
            'target_id' => $this->candidate->id->value,
            'eligible' => $this->eligible,
            'matched_capabilities' => $this->matchedCapabilities,
            'rejection_reasons' => $this->rejectionReasons,
            'policy_checks' => $this->policyChecks,
            'rank' => $this->rank,
            'rank_key' => $this->rankKey,
            'capability_snapshot_version' => $this->candidate->capabilitySnapshot === null ? $this->candidate->capabilitySnapshotId : $this->candidate->capabilitySnapshot->version,
        ];
    }
}
