<?php

declare(strict_types=1);

namespace Sifrious\Logres;

enum RunnerLeaseAcknowledgementStatus: string
{
    case Acknowledged = 'acknowledged';
    case Duplicate = 'duplicate';
    case Conflict = 'conflict';
    case Rejected = 'rejected';
}
