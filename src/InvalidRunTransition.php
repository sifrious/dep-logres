<?php

declare(strict_types=1);

namespace Sifrious\Logres;

use DomainException;

final class InvalidRunTransition extends DomainException
{
    public readonly string $reason;

    public function __construct(string $message, string $reason = 'invalid_transition')
    {
        parent::__construct($message);
        $this->reason = $reason;
    }

    public static function between(RunStatus $from, RunStatus $to): self
    {
        return new self(
            "Run status cannot transition from {$from->value} to {$to->value}.",
            $from->isTerminal() ? 'already_terminal' : 'invalid_transition',
        );
    }
}
