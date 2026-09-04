<?php

declare(strict_types=1);

namespace Sifrious\Logres;

use InvalidArgumentException;

final readonly class AgentStepId
{
    public function __construct(public string $value)
    {
        if (trim($value) === '') {
            throw new InvalidArgumentException('Agent Step identity cannot be empty.');
        }
    }

    public static function forSequence(RunId $runId, AttemptId $attemptId, int $sequence): self
    {
        if ($sequence < 1) {
            throw new InvalidArgumentException('Agent Step sequence starts at one.');
        }

        return new self('agent-step:sha256:'.hash('sha256', implode("\0", [
            $runId->value,
            $attemptId->value,
            (string) $sequence,
        ])));
    }
}
