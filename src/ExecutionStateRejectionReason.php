<?php

declare(strict_types=1);

namespace Sifrious\Logres;

enum ExecutionStateRejectionReason: string
{
    case InvalidTransition = 'invalid_transition';
    case AlreadyTerminal = 'already_terminal';
    case StaleAttempt = 'stale_attempt';
    case ForeignLease = 'foreign_lease';
    case LeaseExpired = 'lease_expired';
    case NotLeaseHolder = 'not_lease_holder';
    case AlreadyLeased = 'already_leased';
    case NotEligibleForLease = 'not_eligible_for_lease';
    case StaleState = 'stale_state';
    case RetryExhausted = 'retry_exhausted';
    case PermanentFailure = 'permanent_failure';
    case ReconciliationRequired = 'reconciliation_required';
    case CancellationUnauthorized = 'cancellation_unauthorized';
    case CancellationPending = 'cancellation_pending';
    case CancellationConflict = 'cancellation_conflict';
}
