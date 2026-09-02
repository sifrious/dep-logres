<?php

declare(strict_types=1);

namespace Sifrious\Logres;

enum CancellationStatus: string
{
    case Requested = 'requested';
    case Confirmed = 'confirmed';
}
