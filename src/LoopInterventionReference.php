<?php

declare(strict_types=1);

namespace Sifrious\Logres;

use InvalidArgumentException;

/**
 * References an Elwin- or policy-owned determination without reproducing its state machine.
 */
final readonly class LoopInterventionReference
{
    public function __construct(
        public LoopDisposition $disposition,
        public string $reference,
        public ?TaskId $taskId = null,
    ) {
        if (! in_array($disposition, [LoopDisposition::Clarify, LoopDisposition::Escalate, LoopDisposition::Stop], true)) {
            throw new InvalidArgumentException('Only clarify, escalate, or stop can be supplied as an external Loop intervention.');
        }

        if (trim($reference) === '') {
            throw new InvalidArgumentException('A Loop intervention requires its owning decision reference.');
        }
    }
}
