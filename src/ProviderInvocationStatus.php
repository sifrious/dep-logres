<?php

declare(strict_types=1);

namespace Sifrious\Logres;

enum ProviderInvocationStatus: string
{
    case Reserved = 'reserved';
    case Dispatching = 'dispatching';
    case Accepted = 'accepted';
    case Rejected = 'rejected';
    case Unavailable = 'unavailable';
    case AcknowledgementUncertain = 'acknowledgement_uncertain';
    case BindingConflict = 'binding_conflict';
    case Duplicate = 'duplicate';
}
