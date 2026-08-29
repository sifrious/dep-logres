<?php

declare(strict_types=1);

namespace Sifrious\Logres;

enum RunnerTerminalStatus: string
{
    case Success = 'success';
    case Failure = 'failure';
    case Cancelled = 'cancelled';
    case TimedOut = 'timed_out';
    case Rejected = 'rejected';
}
