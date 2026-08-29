<?php

declare(strict_types=1);

namespace Sifrious\Logres;

final readonly class ExecutionStateReadModel
{
    public function __construct(
        public string $runId,
        public string $status,
        public string $createdAt,
        public ?string $scheduledAt,
        public ?string $startedAt,
        public ?string $finishedAt,
        public ?string $failureReason,
        public ?string $terminalResultReference,
        public ?array $currentAttempt,
        public array $attempts,
        public int $version,
    ) {}

    public static function fromState(ExecutionState $state): self
    {
        $attempts = array_map(self::attempt(...), $state->attempts);
        $current = $state->currentAttempt();

        return new self(
            $state->runId->value,
            $state->status->value,
            $state->createdAt->format(DATE_ATOM),
            $state->scheduledAt?->format(DATE_ATOM),
            $state->startedAt?->format(DATE_ATOM),
            $state->finishedAt?->format(DATE_ATOM),
            $state->failureReason,
            $state->terminalResultReference,
            $current === null ? null : self::attempt($current),
            $attempts,
            $state->version,
        );
    }

    private static function attempt(ExecutionAttempt $attempt): array
    {
        return [
            'id' => $attempt->id->value,
            'run_id' => $attempt->runId->value,
            'number' => $attempt->number,
            'status' => $attempt->status->value,
            'previous_attempt_id' => $attempt->previousAttemptId?->value,
            'created_at' => $attempt->createdAt->format(DATE_ATOM),
            'started_at' => $attempt->startedAt?->format(DATE_ATOM),
            'finished_at' => $attempt->finishedAt?->format(DATE_ATOM),
            'failure_reason' => $attempt->failureReason,
            'leases' => array_map(static fn (ExecutionLease $lease): array => [
                'id' => $lease->id->value,
                'attempt_id' => $lease->attemptId->value,
                'holder' => $lease->holder->value,
                'status' => $lease->status->value,
                'acquired_at' => $lease->acquiredAt->format(DATE_ATOM),
                'expires_at' => $lease->expiresAt->format(DATE_ATOM),
                'renewed_at' => $lease->renewedAt?->format(DATE_ATOM),
                'released_at' => $lease->releasedAt?->format(DATE_ATOM),
            ], $attempt->leases),
        ];
    }
}
