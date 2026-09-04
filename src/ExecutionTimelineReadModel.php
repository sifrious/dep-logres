<?php

declare(strict_types=1);

namespace Sifrious\Logres;

final readonly class ExecutionTimelineReadModel
{
    /** @param list<array<string, mixed>> $items @param list<array{int, int}> $missingSequenceRanges */
    public function __construct(
        public string $invocationId,
        public string $providerExecutionId,
        public string $runId,
        public string $taskId,
        public string $attemptId,
        public string $projectedRunStatus,
        public array $items,
        public array $missingSequenceRanges,
    ) {}

    public static function fromProviderLog(ProviderExecutionEventLog $log): self
    {
        $items = array_map(
            static fn (ExecutionEvent $event): array => $event->toArray(),
            $log->events,
        );

        usort($items, static fn (array $a, array $b): int => $a['sequence'] <=> $b['sequence']);

        return new self(
            invocationId: $log->invocationId,
            providerExecutionId: $log->providerExecutionId->canonical(),
            runId: $log->runId->value,
            taskId: $log->taskId->value,
            attemptId: $log->attemptId->value,
            projectedRunStatus: $log->projectedRunStatus()->value,
            items: $items,
            missingSequenceRanges: $log->missingSequenceRanges,
        );
    }
}
