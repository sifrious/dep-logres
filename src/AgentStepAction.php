<?php

declare(strict_types=1);

namespace Sifrious\Logres;

enum AgentStepAction: string
{
    case Wait = 'wait';
    case Lease = 'lease';
    case Invoke = 'invoke';
    case Reconcile = 'reconcile';
    case Retry = 'retry';
    case Complete = 'complete';
    case Escalate = 'escalate';
    case Stop = 'stop';

    public function requiresEffect(): bool
    {
        return $this !== self::Wait;
    }
}
