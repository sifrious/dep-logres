<?php

declare(strict_types=1);

namespace Sifrious\Logres;

enum TargetAvailability: string
{
    case Available = 'available';
    case Busy = 'busy';
    case Offline = 'offline';
    case Degraded = 'degraded';
}
