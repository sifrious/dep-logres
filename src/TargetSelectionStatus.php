<?php

declare(strict_types=1);

namespace Sifrious\Logres;

enum TargetSelectionStatus: string
{
    case Selected = 'selected';
    case Rejected = 'rejected';
}
