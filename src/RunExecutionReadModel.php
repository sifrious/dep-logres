<?php

declare(strict_types=1);

namespace Sifrious\Logres;

final readonly class RunExecutionReadModel
{
    public function __construct(
        public string $runId,
        public array $events,
        public string $stdout,
        public string $stderr,
        public ?array $result,
        public bool $cancellationRequested,
    ) {}

    public static function fromRecord(RunExecutionRecord $record): self
    {
        return new self(
            $record->runId->value,
            array_map(static fn (ExecutionEvent $event) => $event->toArray(), $record->events),
            $record->stdout,
            $record->stderr,
            $record->result === null ? null : [
                'status' => $record->result->status->value,
                'exit_code' => $record->result->exitCode,
                'stdout' => $record->result->stdout,
                'stderr' => $record->result->stderr,
                'reason' => $record->result->reason,
            ],
            $record->cancellationRequested,
        );
    }
}
