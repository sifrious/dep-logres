<?php

declare(strict_types=1);

namespace Sifrious\Logres;

use InvalidArgumentException;

final readonly class RetryPolicy
{
    public function __construct(public int $maximumAttempts)
    {
        if ($maximumAttempts < 1) {
            throw new InvalidArgumentException('Retry policy must allow at least one Attempt.');
        }
    }

    public function decide(ExecutionState $state, FailureClassification $classification): RecoveryAction
    {
        if ($classification === FailureClassification::AcknowledgementUncertain) {
            return RecoveryAction::Reconcile;
        }
        if ($classification === FailureClassification::Permanent || count($state->attempts) >= $this->maximumAttempts) {
            return RecoveryAction::Fail;
        }
        return RecoveryAction::Retry;
    }
}
