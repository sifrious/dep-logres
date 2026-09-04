<?php

declare(strict_types=1);

namespace Sifrious\Logres;

use InvalidArgumentException;

final readonly class RunnerPollResponse
{
    private function __construct(
        public RunnerPollResponseStatus $status,
        public ?ExecutionEnvelope $envelope = null,
        public ?RunnerLease $lease = null,
        public ?int $retryAfterSeconds = null,
    ) {}

    public static function noWork(int $retryAfterSeconds): self
    {
        if ($retryAfterSeconds < 1) {
            throw new InvalidArgumentException('No-work responses require a positive retry delay.');
        }

        return new self(RunnerPollResponseStatus::NoWork, retryAfterSeconds: $retryAfterSeconds);
    }

    public static function lease(ExecutionEnvelope $envelope, RunnerLease $lease): self
    {
        if ($lease->status !== RunnerLeaseStatus::Offered) {
            throw new InvalidArgumentException('Runner poll lease responses require an offered lease.');
        }
        if ($lease->runId->value !== $envelope->runId->value
            || $lease->id !== $envelope->leaseId->value
            || $lease->runnerId !== $envelope->targetRunnerId->value) {
            throw new InvalidArgumentException('Runner poll lease responses require matching immutable run, lease, and runner identities.');
        }

        return new self(RunnerPollResponseStatus::Lease, $envelope, $lease);
    }
}
