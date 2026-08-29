<?php

declare(strict_types=1);

namespace Sifrious\Logres;

use DateTimeImmutable;
use InvalidArgumentException;

final readonly class RunExecutionRecord
{
    /** @param list<ExecutionEvent> $events */
    public function __construct(
        public RunId $runId,
        public array $events = [],
        public string $stdout = '',
        public string $stderr = '',
        public ?RunResult $result = null,
        public bool $cancellationRequested = false,
        public ?DateTimeImmutable $cancelledRequestedAt = null,
    ) {}

    public function append(ExecutionEvent $event): self
    {
        foreach ($this->events as $existing) {
            if ($existing->sequence === $event->sequence) {
                if ($existing->toArray() !== $event->toArray()) {
                    throw new InvalidArgumentException('An event sequence cannot be reused with different content.');
                }
                return $this;
            }
        }
        $expected = count($this->events) + 1;
        if ($event->sequence !== $expected || $this->result !== null) {
            throw new InvalidArgumentException('Execution events must be contiguous and cannot follow a result.');
        }

        return new self($this->runId, [...$this->events, $event], $this->stdout, $this->stderr, null, $this->cancellationRequested, $this->cancelledRequestedAt);
    }

    public function output(string $stream, string $chunk): self
    {
        if ($this->result !== null || ! in_array($stream, ['stdout', 'stderr'], true)) {
            throw new InvalidArgumentException('Output requires an active execution and a known stream.');
        }
        return new self($this->runId, $this->events, $stream === 'stdout' ? $this->stdout.$chunk : $this->stdout, $stream === 'stderr' ? $this->stderr.$chunk : $this->stderr, null, $this->cancellationRequested, $this->cancelledRequestedAt);
    }

    public function cancel(DateTimeImmutable $now): self
    {
        if ($this->cancellationRequested || $this->result !== null) {
            return $this;
        }
        return new self($this->runId, $this->events, $this->stdout, $this->stderr, null, true, $now);
    }

    public function finish(RunResult $result): self
    {
        if ($this->result !== null) {
            if ($this->result != $result) {
                throw new InvalidArgumentException('A terminal result is immutable.');
            }
            return $this;
        }
        if ($this->cancellationRequested && $result->status !== RunStatus::Cancelled) {
            throw new InvalidArgumentException('A cancellation request must resolve as cancelled.');
        }
        return new self($this->runId, $this->events, $this->stdout, $this->stderr, $result, $this->cancellationRequested, $this->cancelledRequestedAt);
    }
}
