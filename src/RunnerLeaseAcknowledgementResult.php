<?php

declare(strict_types=1);

namespace Sifrious\Logres;

use InvalidArgumentException;

final readonly class RunnerLeaseAcknowledgementResult
{
    public function __construct(
        public RunnerLeaseAcknowledgementStatus $status,
        public RunnerLease $lease,
        public ?string $detail = null,
    ) {
        if ($status === RunnerLeaseAcknowledgementStatus::Acknowledged
            && $lease->status !== RunnerLeaseStatus::Acknowledged) {
            throw new InvalidArgumentException('An acknowledged acknowledgement result requires an acknowledged lease.');
        }
    }
}
