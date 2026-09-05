<?php

declare(strict_types=1);

namespace Sifrious\Logres;

use InvalidArgumentException;

final readonly class LoopPolicyDetermination
{
    /**
     * @param list<LoopPolicyClause> $exhaustedClauses
     * @param list<EvidenceReference> $evidence
     */
    public function __construct(
        public string $policyName,
        public string $policyVersion,
        public string $observedAt,
        public LoopPolicyOutcome $outcome,
        public LoopPolicyClause $clause,
        public string $reason,
        public LoopBudgetRemaining $remaining,
        public array $exhaustedClauses = [],
        public array $evidence = [],
    ) {
        if (trim($policyName) === '' || trim($policyVersion) === '' || trim($reason) === '') {
            throw new InvalidArgumentException('A loop determination requires policy provenance and a reason.');
        }
        if ($outcome === LoopPolicyOutcome::PolicyExhausted && $exhaustedClauses === []) {
            throw new InvalidArgumentException('A policy-exhausted determination must name exhausted clauses.');
        }
    }

    public function isTerminal(): bool
    {
        return $this->outcome->isTerminal();
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'policy_name' => $this->policyName,
            'policy_version' => $this->policyVersion,
            'observed_at' => $this->observedAt,
            'outcome' => $this->outcome->value,
            'clause' => $this->clause->value,
            'reason' => $this->reason,
            'remaining' => $this->remaining->toArray(),
            'exhausted_clauses' => array_map(
                static fn (LoopPolicyClause $clause): string => $clause->value,
                $this->exhaustedClauses,
            ),
            'evidence' => $this->evidence,
        ];
    }
}
