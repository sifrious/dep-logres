<?php

declare(strict_types=1);

namespace Sifrious\Logres;

enum RunnerLocalStage: string
{
    case Received = 'received';
    case Accepted = 'accepted';
    case Invoking = 'invoking';
    case Reporting = 'reporting';
    case Terminal = 'terminal';
}
