<?php

declare(strict_types=1);

namespace Sifrious\Logres;

use InvalidArgumentException;

final readonly class ProviderExecutionEventLog
{
    /**
     * @param list<array<string, mixed>> $providerEnvelopes
     * @param list<ExecutionEvent> $events
     * @param array<string, true> $eventIdentities
     * @param array<int, string> $eventIdentityBySequence
     * @param list<array{int, int}> $missingSequenceRanges
     * @param array<string, RunArtifactAttachment> $artifactAttachments
     */
    public function __construct(
        public string $invocationId,
        public string $provider,
        public ProviderExecutionId $providerExecutionId,
        public RunId $runId,
        public TaskId $taskId,
        public AttemptId $attemptId,
        public array $providerEnvelopes = [],
        public array $events = [],
        public array $eventIdentities = [],
        public array $eventIdentityBySequence = [],
        public int $highestSequence = 0,
        public array $missingSequenceRanges = [],
        public ?int $terminalSequence = null,
        public array $artifactAttachments = [],
    ) {
        if (trim($this->invocationId) === '' || trim($this->provider) === '') {
            throw new InvalidArgumentException('Provider event logs require invocation and provider identity.');
        }
        if ($this->providerExecutionId->provider !== $this->provider) {
            throw new InvalidArgumentException('Provider event log identity must match provider execution namespace.');
        }
    }

    public static function begin(
        string $invocationId,
        string $provider,
        string $providerExecutionId,
        string $runId,
        string $taskId,
        string $attemptId,
    ): self {
        return new self(
            invocationId: $invocationId,
            provider: $provider,
            providerExecutionId: new ProviderExecutionId($provider, $providerExecutionId),
            runId: new RunId($runId),
            taskId: new TaskId($taskId),
            attemptId: new AttemptId($attemptId),
        );
    }

    public function hasEventIdentity(string $identity): bool
    {
        return isset($this->eventIdentities[$identity]);
    }

    public function hasSequence(int $sequence): bool
    {
        return isset($this->eventIdentityBySequence[$sequence]);
    }

    public function expectsAssociation(ProviderExecutionEventEnvelope $envelope): bool
    {
        return $this->invocationId === $envelope->invocationId
            && $this->provider === $envelope->provider
            && $this->providerExecutionId->canonical() === $envelope->providerExecutionId->canonical()
            && $this->runId->value === $envelope->runId->value
            && $this->taskId->value === $envelope->taskId->value
            && $this->attemptId->value === $envelope->attemptId->value;
    }

    public function projectedRunStatus(): RunStatus
    {
        if ($this->events === []) {
            return RunStatus::Pending;
        }

        $resolved = null;
        if ($this->terminalSequence !== null) {
            foreach ($this->events as $event) {
                if ($event->sequence === $this->terminalSequence) {
                    $resolved = $event;
                    break;
                }
            }
        }
        if ($resolved === null) {
            $bySequence = $this->events;
            usort($bySequence, static fn (ExecutionEvent $left, ExecutionEvent $right): int => $left->sequence <=> $right->sequence);
            $resolved = $bySequence[array_key_last($bySequence)];
        }

        return self::statusForEventType($resolved->type);
    }

    public function appendEnvelopeAndEvent(ProviderExecutionEventEnvelope $envelope, ExecutionEvent $event, string $eventIdentity, ?RunArtifactAttachment $attachment = null): self
    {
        $identities = $this->eventIdentities;
        $identities[$eventIdentity] = true;
        $sequenceIndex = $this->eventIdentityBySequence;
        $sequenceIndex[$event->sequence] = $eventIdentity;
        ksort($sequenceIndex);
        $attachments = $this->attachArtifact($attachment);

        return new self(
            invocationId: $this->invocationId,
            provider: $this->provider,
            providerExecutionId: $this->providerExecutionId,
            runId: $this->runId,
            taskId: $this->taskId,
            attemptId: $this->attemptId,
            providerEnvelopes: [...$this->providerEnvelopes, $envelope->toArray()],
            events: [...$this->events, $event],
            eventIdentities: $identities,
            eventIdentityBySequence: $sequenceIndex,
            highestSequence: max($this->highestSequence, $event->sequence),
            missingSequenceRanges: self::resolveMissingRanges(self::registerMissingRange($this->missingSequenceRanges, $this->highestSequence + 1, $event->sequence - 1), $event->sequence),
            terminalSequence: self::terminalSequence($this->terminalSequence, $event),
            artifactAttachments: $attachments,
        );
    }

    public function appendEnvelopeOnly(ProviderExecutionEventEnvelope $envelope): self
    {
        return new self(
            invocationId: $this->invocationId,
            provider: $this->provider,
            providerExecutionId: $this->providerExecutionId,
            runId: $this->runId,
            taskId: $this->taskId,
            attemptId: $this->attemptId,
            providerEnvelopes: [...$this->providerEnvelopes, $envelope->toArray()],
            events: $this->events,
            eventIdentities: $this->eventIdentities,
            eventIdentityBySequence: $this->eventIdentityBySequence,
            highestSequence: $this->highestSequence,
            missingSequenceRanges: $this->missingSequenceRanges,
            terminalSequence: $this->terminalSequence,
            artifactAttachments: $this->artifactAttachments,
        );
    }

    /** @return array<string, RunArtifactAttachment> */
    private function attachArtifact(?RunArtifactAttachment $attachment): array
    {
        if ($attachment === null) {
            return $this->artifactAttachments;
        }

        $key = $attachment->artifact->id;
        if (! isset($this->artifactAttachments[$key])) {
            return [...$this->artifactAttachments, $key => $attachment];
        }

        $existing = $this->artifactAttachments[$key];
        if ($existing->toArray() === $attachment->toArray()) {
            return $this->artifactAttachments;
        }

        throw new InvalidArgumentException(
            sprintf(
                'Artifact [%s] is immutable once attached to Run [%s]; attach a new artifact and link via supersedes_artifact_id.',
                $key,
                $this->runId->value,
            )
        );
    }

    /** @param list<array{int, int}> $ranges @return list<array{int, int}> */
    private static function registerMissingRange(array $ranges, int $start, int $end): array
    {
        if ($start > $end) {
            return $ranges;
        }

        $ranges[] = [$start, $end];
        usort($ranges, static fn (array $a, array $b): int => $a[0] <=> $b[0]);
        $merged = [];
        foreach ($ranges as [$rangeStart, $rangeEnd]) {
            if ($merged === []) {
                $merged[] = [$rangeStart, $rangeEnd];
                continue;
            }
            [$lastStart, $lastEnd] = $merged[array_key_last($merged)];
            if ($rangeStart <= $lastEnd + 1) {
                $merged[array_key_last($merged)] = [$lastStart, max($lastEnd, $rangeEnd)];
                continue;
            }
            $merged[] = [$rangeStart, $rangeEnd];
        }

        return $merged;
    }

    /** @param list<array{int, int}> $ranges @return list<array{int, int}> */
    private static function resolveMissingRanges(array $ranges, int $sequence): array
    {
        $resolved = [];
        foreach ($ranges as [$start, $end]) {
            if ($sequence < $start || $sequence > $end) {
                $resolved[] = [$start, $end];
                continue;
            }
            if ($sequence > $start) {
                $resolved[] = [$start, $sequence - 1];
            }
            if ($sequence < $end) {
                $resolved[] = [$sequence + 1, $end];
            }
        }

        return $resolved;
    }

    private static function terminalSequence(?int $existing, ExecutionEvent $event): ?int
    {
        if ($existing !== null) {
            return $existing;
        }

        return match ($event->type) {
            ExecutionEventType::TaskCompleted->value,
            ExecutionEventType::TaskFailed->value,
            ExecutionEventType::TaskTimedOut->value,
            ExecutionEventType::TaskCancelled->value => $event->sequence,
            default => null,
        };
    }

    private static function statusForEventType(string $type): RunStatus
    {
        return match ($type) {
            ExecutionEventType::TaskCompleted->value => RunStatus::Succeeded,
            ExecutionEventType::TaskFailed->value => RunStatus::Failed,
            ExecutionEventType::TaskTimedOut->value => RunStatus::TimedOut,
            ExecutionEventType::TaskCancelled->value => RunStatus::Cancelled,
            ExecutionEventType::InputRequested->value => RunStatus::NeedsInput,
            ExecutionEventType::TargetAccepted->value => RunStatus::Preparing,
            default => RunStatus::Running,
        };
    }
}
