<?php

declare(strict_types=1);

namespace Sifrious\Logres;

enum InvariantPreflightPhase: int
{
    case Authorization = 10;
    case Workspace = 20;
    case Provenance = 30;
}
