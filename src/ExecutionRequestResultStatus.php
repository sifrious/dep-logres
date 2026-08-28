<?php

declare(strict_types=1);

namespace Sifrious\Logres;

enum ExecutionRequestResultStatus: string
{
    case Accepted = 'accepted';
    case Rejected = 'rejected';
    case PersistenceFailed = 'persistence_failed';
}
