<?php

declare(strict_types=1);

namespace Sifrious\Logres;

use DateTimeImmutable;

final class SequencedRunnerObserver implements RunnerRuntimeObserver
{
    private int $sequence = 0;

    public function __construct(
        private readonly ExecutionEnvelope $envelope,
        private readonly RunnerIdentity $runnerId,
        private readonly RunnerEventSink $sink,
        private readonly RunnerLifecycle $lifecycle,
        private readonly DateTimeImmutable $startedAt,
    ) {}

    public function event(RunnerEventType $type, array $payload = []): void
    {
        $sequence = ++$this->sequence;
        $id = hash('sha256', RunnerLocalRecord::key($this->envelope).'|'.$sequence);
        $this->sink->emit(new RunnerEvent($id, $this->envelope->runId, $this->envelope->attemptId, $this->envelope->leaseId, $this->runnerId, $sequence, $this->startedAt, $type, $payload));
    }

    public function cancellationRequested(): bool
    {
        return $this->lifecycle->cancellationRequested($this->envelope);
    }
}
