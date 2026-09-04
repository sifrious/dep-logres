<?php

declare(strict_types=1);

namespace Sifrious\Logres;

enum RunnerRejectionReason: string
{
    case WrongRunner = 'wrong_runner';
    case Expired = 'expired';
    case Malformed = 'malformed';
    case Unauthenticated = 'unauthenticated';
    case UnsupportedProtocolVersion = 'unsupported_protocol_version';
    case Unauthorized = 'unauthorized';
    case WorkspaceUnavailable = 'workspace_unavailable';
    case WorkspaceMismatch = 'workspace_mismatch';
    case RuntimeUnavailable = 'runtime_unavailable';
    case CapabilityMismatch = 'capability_mismatch';
    case DuplicateOrAlreadyProcessed = 'duplicate_or_already_processed';
    case IdempotencyConflict = 'idempotency_conflict';
    case InvalidLifecycleState = 'invalid_lifecycle_state';
}
