<?php

declare(strict_types=1);

namespace Sifrious\Logres;

enum HumanInputResolution: string
{
    case Answered = 'answered';
    case TimedOut = 'timed_out';
    case Cancelled = 'cancelled';
}
