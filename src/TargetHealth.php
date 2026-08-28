<?php

declare(strict_types=1);

namespace Sifrious\Logres;

enum TargetHealth: string
{
    case Healthy = 'healthy';
    case Degraded = 'degraded';
    case Unhealthy = 'unhealthy';
    case Unknown = 'unknown';
}
