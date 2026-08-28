<?php

declare(strict_types=1);

namespace Sifrious\Logres;

enum TaskStatus: string
{
    case Planned = 'planned';
    case Running = 'running';
    case WaitingForInput = 'waiting_for_input';
    case Succeeded = 'succeeded';
    case Failed = 'failed';
    case Skipped = 'skipped';
    case Canceled = 'canceled';
}
