<?php

declare(strict_types=1);

namespace Sifrious\Logres;

enum TaskReadiness: string
{
    case Ready = 'ready';
    case Blocked = 'blocked';
    case Running = 'running';
    case WaitingForInput = 'waiting_for_input';
    case Succeeded = 'succeeded';
    case Failed = 'failed';
    case Skipped = 'skipped';
    case Canceled = 'canceled';
}
