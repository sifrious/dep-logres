<?php

declare(strict_types=1);

namespace Sifrious\Logres;

enum ExecutionClass: string
{
    case Local = 'local';
    case ManagedCloud = 'managed-cloud';
    case CustomerOwned = 'customer-owned';
    case ProviderHosted = 'provider-hosted';
}
