<?php

declare(strict_types=1);

namespace Sifrious\Logres;

enum ProviderBindingOutcome: string
{
    case Acknowledged = 'acknowledged';
    case Duplicate = 'duplicate';
    case Conflict = 'conflict';
    case ReconciliationRequired = 'reconciliation_required';
}
