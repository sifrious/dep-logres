<?php

declare(strict_types=1);

namespace Sifrious\Logres;

use DateTimeImmutable;
use InvalidArgumentException;
use Sifrious\Elwin\Handoff\ResumableHandoff;
use Sifrious\Elwin\Handoff\ResumeContext;
use Sifrious\ReferenceContract\CrossPackageReference;

/** Logres lifecycle state referencing, but never copying, an Elwin handoff. */
final readonly class NeedsInputPause
{
    public function __construct(
        public CrossPackageReference $handoff,
        public ResumeContext $resumeContext,
        public AttemptId $attemptId,
        public DateTimeImmutable $pausedAt,
        public NeedsInputPauseStatus $status = NeedsInputPauseStatus::Waiting,
        public ?DateTimeImmutable $resolvedAt = null,
        public ?string $resolutionOperationId = null,
    ) {
        if (($this->status === NeedsInputPauseStatus::Waiting) !== ($this->resolvedAt === null)) {
            throw new InvalidArgumentException('Only a waiting NeedsInput pause may be unresolved.');
        }
        if (($this->resolvedAt === null) !== ($this->resolutionOperationId === null)) {
            throw new InvalidArgumentException('A resolved NeedsInput pause requires time and operation identity.');
        }
    }

    public static function fromHandoff(ResumableHandoff $handoff, AttemptId $attemptId): self
    {
        return new self($handoff->reference(), $handoff->resumeContext, $attemptId, $handoff->requestedAt);
    }

    public function matches(ResumableHandoff $handoff): bool
    {
        return $this->handoff->equals($handoff->reference())
            && $this->resumeContext == $handoff->resumeContext;
    }

    public function resolve(NeedsInputPauseStatus $status, string $operationId, DateTimeImmutable $at): self
    {
        if ($status === NeedsInputPauseStatus::Waiting || trim($operationId) === '') {
            throw new InvalidArgumentException('A NeedsInput resolution requires a terminal pause status and operation identity.');
        }
        if ($this->status !== NeedsInputPauseStatus::Waiting) {
            if ($this->status === $status && $this->resolutionOperationId === $operationId) {
                return $this;
            }
            throw ExecutionStateRejected::because(ExecutionStateRejectionReason::InputQuestionConflict, 'A resolved handoff pause cannot be replaced.');
        }

        return new self($this->handoff, $this->resumeContext, $this->attemptId, $this->pausedAt, $status, $at, $operationId);
    }
}
