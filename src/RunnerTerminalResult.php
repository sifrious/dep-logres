<?php

declare(strict_types=1);

namespace Sifrious\Logres;

use DateTimeImmutable;
use InvalidArgumentException;

final readonly class RunnerTerminalResult
{
    /** @param list<string> $artifactReferences @param list<string> $evidenceReferences */
    public function __construct(
        public RunId $runId,
        public AttemptId $attemptId,
        public LeaseId $leaseId,
        public RunnerIdentity $runnerId,
        public RunnerTerminalStatus $status,
        public string $runtime,
        public string $adapter,
        public WorkspaceAuthority $workspaceIdentity,
        public DateTimeImmutable $startedAt,
        public DateTimeImmutable $finishedAt,
        public ?int $exitCode = null,
        public array $artifactReferences = [],
        public array $evidenceReferences = [],
        public ?string $failureCategory = null,
        public ?string $failureDetail = null,
    ) {
        if ($finishedAt < $startedAt || ($status === RunnerTerminalStatus::Success && $exitCode !== 0)) {
            throw new InvalidArgumentException('A terminal runner result requires coherent timing and outcome data.');
        }
    }
}
