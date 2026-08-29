<?php

declare(strict_types=1);

namespace Sifrious\Logres;

enum CandidateRejectionReason: string
{
    case UnauthorizedTarget = 'unauthorized_target';
    case UnauthorizedWorkspace = 'unauthorized_workspace';
    case RepositoryMismatch = 'repository_mismatch';
    case WorkspaceMismatch = 'workspace_mismatch';
    case WrongProviderAccount = 'wrong_provider_account';
    case WrongProviderProject = 'wrong_provider_project';
    case MissingCapability = 'missing_capability';
    case UnsupportedRuntime = 'unsupported_runtime';
    case Offline = 'offline';
    case Unavailable = 'unavailable';
    case DegradedNotAllowed = 'degraded_not_allowed';
    case StaleObservation = 'stale_observation';
    case ConcurrencyExhausted = 'concurrency_exhausted';
    case ExecutionClassDisallowed = 'execution_class_disallowed';
    case PrivacyConstraintFailed = 'privacy_constraint_failed';
    case NetworkConstraintFailed = 'network_constraint_failed';
    case CallerInventedTarget = 'caller_invented_target';
    case TargetNotInInventory = 'target_not_in_inventory';
    case AmbiguousSelection = 'ambiguous_selection';
    case DuplicateInventoryIdentity = 'duplicate_inventory_identity';
}
