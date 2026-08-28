<?php

declare(strict_types=1);

namespace Sifrious\Logres;

enum TaskPlanningStatus: string
{
    case Accepted = 'accepted';
    case Rejected = 'rejected';
}
