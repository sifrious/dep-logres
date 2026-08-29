<?php

declare(strict_types=1);

namespace Sifrious\Logres;

final readonly class CandidateEvaluation
{
    public array $rejectionReasons;

    public function __construct(
        public ExecutionTargetCandidate $candidate,
        array $rejectionReasons,
    ) {
        $unique = [];
        foreach ($rejectionReasons as $reason) {
            if (! $reason instanceof CandidateRejectionReason) {
                throw new \InvalidArgumentException('Candidate rejection reasons must use the stable enum.');
            }
            $unique[$reason->value] = $reason;
        }
        ksort($unique);
        $this->rejectionReasons = array_values($unique);
    }

    public function eligible(): bool
    {
        return $this->rejectionReasons === [];
    }

    public function canonicalData(): array
    {
        return [
            'target_id' => $this->candidate->id->value,
            'eligible' => $this->eligible(),
            'rejection_reasons' => array_map(static fn (CandidateRejectionReason $reason): string => $reason->value, $this->rejectionReasons),
            'observed_capabilities' => $this->candidate->capabilities,
            'health' => $this->candidate->health->value,
            'availability' => $this->candidate->availability->value,
            'observed_at' => $this->candidate->observedAt,
        ];
    }
}
