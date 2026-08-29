<?php

declare(strict_types=1);

namespace Sifrious\Logres;

enum RunnerCompatibilityFailure: string
{
    case RuntimeAdapterProfile = 'runtime_adapter_profile';
    case ProtocolVersion = 'protocol_version';
    case Capability = 'capability';
    case WorkspaceIdentity = 'workspace_identity';
    case AuthorizationGrant = 'authorization_grant';
}
