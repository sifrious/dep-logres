<?php

declare(strict_types=1);

namespace Sifrious\Logres;

enum NeedsInputPauseStatus: string
{
    case Waiting = 'waiting';
    case Resumed = 'resumed';
    case TimedOut = 'timed_out';
    case Cancelled = 'cancelled';
}
