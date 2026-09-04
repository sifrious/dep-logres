<?php

declare(strict_types=1);

namespace Sifrious\Logres;

enum RunnerTerminalResultDeliveryStatus: string
{
    case Accepted = 'accepted';
    case Duplicate = 'duplicate';
    case Retry = 'retry';
}
