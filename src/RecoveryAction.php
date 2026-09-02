<?php

declare(strict_types=1);

namespace Sifrious\Logres;

enum RecoveryAction: string
{
    case Retry = 'retry';
    case Reconcile = 'reconcile';
    case Fail = 'fail';
}
