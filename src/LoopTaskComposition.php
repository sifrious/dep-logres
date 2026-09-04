<?php

declare(strict_types=1);

namespace Sifrious\Logres;

use InvalidArgumentException;

/**
 * Joins existing task, handoff, external work, execution, result, and verification objects.
 */
final readonly class LoopTaskComposition
{
    public function __construct(
        public TranslatedTask $task,
        public ?LoopHandoffReference $handoff = null,
        public ?LoopExternalWorkReference $externalWork = null,
        public ?Run $run = null,
        public ?RunResult $result = null,
        public ?VerifiedOutcome $verification = null,
    ) {
        if ($handoff !== null && $handoff->taskId?->value !== $task->id->value) {
            throw new InvalidArgumentException('A ticket handoff must originate from its composed task.');
        }

        if ($externalWork !== null && $externalWork->taskId->value !== $task->id->value) {
            throw new InvalidArgumentException('An external work mapping must identify its composed task.');
        }

        if ($run !== null && (
            $run->provenance->taskId->value !== $task->id->value
            || $run->provenance->requestId->value !== $task->requestId->value
        )) {
            throw new InvalidArgumentException('A Run must preserve its composed task and request identities.');
        }

        if ($run !== null && $handoff === null) {
            throw new InvalidArgumentException('Execution cannot precede the task handoff.');
        }

        if ($result !== null && $run === null) {
            throw new InvalidArgumentException('A result requires its owning Run.');
        }

        if ($verification !== null && $result === null) {
            throw new InvalidArgumentException('Verification requires the normalized Run result it evaluates.');
        }
    }

    public function hasVerifiedEvidence(): bool
    {
        return $this->result?->isVerifiedSuccess() === true
            && $this->verification?->isVerifiedSuccess() === true
            && $this->verification->evidence !== [];
    }
}
