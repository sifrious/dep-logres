<?php

declare(strict_types=1);

namespace Sifrious\Logres;

use DomainException;

final class InvalidRunTransition extends DomainException
{
    public static function between(RunStatus $from, RunStatus $to): self
    {
        return new self("Run status cannot transition from {$from->value} to {$to->value}.");
    }
}
