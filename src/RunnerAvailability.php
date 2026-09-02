<?php

declare(strict_types=1);

namespace Sifrious\Logres;

enum RunnerAvailability: string
{
    case Available = 'available';
    case Busy = 'busy';
    case Draining = 'draining';
    case Offline = 'offline';
}
