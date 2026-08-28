<?php

declare(strict_types=1);

namespace Sifrious\Logres;

enum TargetSelectionReason: string
{
    case Automatic = 'automatic';
    case ManualOverride = 'manual_override';
}
