<?php

declare(strict_types=1);

namespace Sifrious\Logres;

enum CancellationKind: string
{
    case Manual = 'manual';
    case Timeout = 'timeout';
}
