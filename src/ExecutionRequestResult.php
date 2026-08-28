<?php

declare(strict_types=1);

namespace Sifrious\Logres;

final readonly class ExecutionRequestResult
{
    public array $failures;

    private function __construct(
        public ExecutionRequestResultStatus $status,
        public ?ExecutionRequestId $requestId,
        array $failures,
    ) {
        $this->failures = array_values($failures);
    }

    public static function accepted(ExecutionRequestId $requestId): self
    {
        return new self(ExecutionRequestResultStatus::Accepted, $requestId, []);
    }

    public static function rejected(array $failures): self
    {
        return new self(ExecutionRequestResultStatus::Rejected, null, $failures);
    }

    public static function persistenceFailed(): self
    {
        return new self(
            ExecutionRequestResultStatus::PersistenceFailed,
            null,
            [new ExecutionRequestFailure('persistence_failed', 'request', 'The execution request could not be preserved.')],
        );
    }

    public function acceptedSuccessfully(): bool
    {
        return $this->status === ExecutionRequestResultStatus::Accepted;
    }
}
