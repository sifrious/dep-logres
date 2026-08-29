<?php

declare(strict_types=1);

namespace Sifrious\Logres;

enum FinalizationStatus: string
{
    case Complete = 'complete';
    case Incomplete = 'incomplete';
}
