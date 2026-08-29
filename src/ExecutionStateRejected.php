<?php

declare(strict_types=1);

namespace Sifrious\Logres;

use DomainException;

final class ExecutionStateRejected extends DomainException
{
    public function __construct(public readonly ExecutionStateRejectionReason $reason, string $message)
    {
        parent::__construct($message);
    }

    public static function because(ExecutionStateRejectionReason $reason, string $message): self
    {
        return new self($reason, $message);
    }
}
