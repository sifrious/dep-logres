<?php

declare(strict_types=1);

namespace Sifrious\Logres;

enum LoopDisposition: string
{
    case Advance = 'advance';
    case Complete = 'complete';
    case Rework = 'rework';
    case Clarify = 'clarify';
    case Escalate = 'escalate';
    case Stop = 'stop';
}
