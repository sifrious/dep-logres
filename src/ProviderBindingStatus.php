<?php

declare(strict_types=1);

namespace Sifrious\Logres;

enum ProviderBindingStatus: string
{
    case NotDispatched = 'not_dispatched';
    case AwaitingAcknowledgement = 'awaiting_acknowledgement';
    case Acknowledged = 'acknowledged';
    case AcknowledgementUncertain = 'acknowledgement_uncertain';
    case ConflictingAcknowledgement = 'conflicting_acknowledgement';
    case ReconciliationRequired = 'reconciliation_required';
}
