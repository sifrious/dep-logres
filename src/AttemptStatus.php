<?php

declare(strict_types=1);

namespace Sifrious\Logres;

enum AttemptStatus: string
{
    case Ready = 'ready';
    case Leased = 'leased';
    case Running = 'running';
    case Succeeded = 'succeeded';
    case Failed = 'failed';
    case Expired = 'expired';
    case ReconciliationRequired = 'reconciliation_required';
    case Cancelled = 'cancelled';
    case TimedOut = 'timed_out';

    public function isTerminal(): bool
    {
        return in_array($this, [self::Succeeded, self::Failed, self::Cancelled, self::TimedOut], true);
    }
}
