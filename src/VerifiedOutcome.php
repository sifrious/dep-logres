<?php

declare(strict_types=1);

namespace Sifrious\Logres;

use InvalidArgumentException;

final readonly class VerifiedOutcome
{
    /** @var list<CheckResult> */
    public array $checks;

    /** @var list<EvidenceReference> */
    public array $evidence;

    /** @param list<mixed> $checks @param list<mixed> $evidence */
    public function __construct(
        public RequiredVerificationOutcome $requiredVerification,
        public VerificationStatus $verificationStatus,
        public string $observedOutcome,
        array $checks,
        array $evidence,
    ) {
        if (trim($this->observedOutcome) === '') {
            throw new InvalidArgumentException('Verified outcomes require an observed outcome summary.');
        }

        foreach ($checks as $check) {
            if (! $check instanceof CheckResult) {
                throw new InvalidArgumentException('Verified outcomes only contain CheckResult values.');
            }
        }
        $this->checks = $checks;

        foreach ($evidence as $reference) {
            if (! $reference instanceof EvidenceReference) {
                throw new InvalidArgumentException('Verified outcomes only contain EvidenceReference values.');
            }
        }
        $this->evidence = $evidence;
    }

    public function isVerifiedSuccess(): bool
    {
        return $this->requiredVerification === RequiredVerificationOutcome::Passed
            && $this->verificationStatus === VerificationStatus::Succeeded;
    }

    /** @return list<RunEvidence> */
    public function toRunEvidence(): array
    {
        $evidence = [];
        foreach ($this->checks as $check) {
            $evidence[] = new RunEvidence(
                kind: 'verification.check',
                reference: "{$check->checkId}:{$check->disposition->value}",
                observedAt: $check->evidence[0]->observedAt ?? gmdate('c'),
            );
        }
        foreach ($this->evidence as $reference) {
            $evidence[] = $reference->toRunEvidence();
        }

        return $evidence;
    }
}
