<?php

declare(strict_types=1);

namespace Sifrious\Logres;

use DateTimeImmutable;

final readonly class RunnerLocalRecord
{
    public function __construct(
        public string $executionKey,
        public string $idempotencyIdentity,
        public RunnerLocalStage $stage,
        public DateTimeImmutable $updatedAt,
        public ?RunnerTerminalResult $terminalResult = null,
    ) {}

    public static function key(ExecutionEnvelope $envelope): string
    {
        return implode('|', [$envelope->runId->value, $envelope->attemptId->value, $envelope->leaseId->value]);
    }
}
