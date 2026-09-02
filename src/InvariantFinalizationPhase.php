<?php

declare(strict_types=1);

namespace Sifrious\Logres;

enum InvariantFinalizationPhase: int
{
    case NormalizeProviderClaim = 10;
    case ObserveEndingState = 20;
    case Verify = 30;
    case AssembleCanonicalResult = 40;
    case PersistOperationalResult = 50;
    case ScheduleHistorianExport = 60;
}
