<?php

declare(strict_types=1);

namespace Sifrious\Logres;

use RuntimeException;
use Sifrious\Elwin\Handoff\ResumableHandoff;

final class NeedsInput extends RuntimeException
{
    public function __construct(public readonly ResumableHandoff $handoff)
    {
        if (! $handoff->isAwaitingResponseAt($handoff->requestedAt)) {
            throw new \InvalidArgumentException('A human gate requires an Elwin handoff awaiting a response.');
        }

        parent::__construct("Execution needs input through Elwin handoff {$handoff->id}.");
    }

    public function payload(): array
    {
        return [
            'status' => RunStatus::NeedsInput->value,
            'handoff' => $this->handoff->reference()->toArray(),
            'resume_context' => $this->handoff->resumeContext->jsonSerialize(),
        ];
    }
}
