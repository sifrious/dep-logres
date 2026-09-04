<?php

declare(strict_types=1);

namespace Sifrious\Logres;

use DateTimeImmutable;
use InvalidArgumentException;

final readonly class RunnerEvent
{
    public function __construct(
        public string $id,
        public RunId $runId,
        public AttemptId $attemptId,
        public LeaseId $leaseId,
        public RunnerIdentity $runnerId,
        public int $sequence,
        public DateTimeImmutable $occurredAt,
        public RunnerEventType $type,
        public array $payload = [],
        public ?StacksExecutionContext $executionIdentity = null,
    ) {
        if (trim($id) === '' || $sequence < 1) {
            throw new InvalidArgumentException('Runner events require a stable identity and positive sequence.');
        }
    }
}
