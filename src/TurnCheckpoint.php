<?php

declare(strict_types=1);

namespace Sifrious\Logres;

use InvalidArgumentException;
use Sifrious\Elwin\Handoff\ResumableHandoff;
use Sifrious\Elwin\Handoff\ResumeContext;
use Sifrious\ReferenceContract\CrossPackageReference;

/** Durable context after all TurnRunner preflight and before-turn handlers completed. */
final readonly class TurnCheckpoint
{
    public function __construct(
        public string $requestIdentity,
        public CrossPackageReference $handoff,
        public ResumeContext $resumeContext,
        public TurnContext $context,
    ) {
        if (trim($this->requestIdentity) === '') {
            throw new InvalidArgumentException('A Turn checkpoint requires request identity.');
        }
        if (! $this->resumeContext->checkpoint->equals($this->reference())) {
            throw new InvalidArgumentException('The Elwin resume context must reference this Turn checkpoint.');
        }
    }

    public static function paused(RunRequest $request, NeedsInput $pause, TurnContext $context): self
    {
        return new self(
            $request->identity(),
            $pause->handoff->reference(),
            $pause->handoff->resumeContext,
            $context,
        );
    }

    public function reference(): CrossPackageReference
    {
        return $this->resumeContext->checkpoint;
    }

    public function matches(RunRequest $request, ResumableHandoff $handoff): bool
    {
        return hash_equals($this->requestIdentity, $request->identity())
            && $handoff->pausedWork->owner === 'sifrious/logres'
            && $handoff->pausedWork->type === 'run'
            && hash_equals($this->requestIdentity, $handoff->pausedWork->id)
            && $this->handoff->equals($handoff->reference())
            && $this->resumeContext == $handoff->resumeContext;
    }
}
