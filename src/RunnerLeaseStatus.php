<?php

declare(strict_types=1);

namespace Sifrious\Logres;

enum RunnerLeaseStatus: string
{
    case Offered = 'offered';
    case Acknowledged = 'acknowledged';
    case Completed = 'completed';
    case Released = 'released';
}
