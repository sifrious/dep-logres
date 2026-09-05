<?php

declare(strict_types=1);

namespace Sifrious\Logres;

/**
 * Binds an existing verification outcome to the Run and task it evaluated.
 */
final readonly class LoopVerificationBinding
{
    public function __construct(
        public TaskId $taskId,
        public RunId $runId,
        public VerifiedOutcome $outcome,
    ) {}
}
