<?php

declare(strict_types=1);

namespace Sifrious\Logres;

enum RunStatus: string
{
    case Pending = 'pending';
    case Preparing = 'preparing';
    case Running = 'running';
    case NeedsInput = 'needs_input';
    case Succeeded = 'succeeded';
    case Failed = 'failed';
    case TimedOut = 'timed_out';
    case Cancelled = 'cancelled';

    public function isTerminal(): bool
    {
        return match ($this) {
            self::Succeeded, self::Failed, self::TimedOut, self::Cancelled => true,
            default => false,
        };
    }
}
