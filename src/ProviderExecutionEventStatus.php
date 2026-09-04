<?php

declare(strict_types=1);

namespace Sifrious\Logres;

enum ProviderExecutionEventStatus: string
{
    case Accepted = 'accepted';
    case Duplicate = 'duplicate';
    case Reordered = 'reordered';
    case GapDetected = 'gap_detected';
    case Late = 'late';
    case UnknownType = 'unknown_type';
    case Forged = 'forged';
}
