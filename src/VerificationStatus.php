<?php

declare(strict_types=1);

namespace Sifrious\Logres;

enum VerificationStatus: string
{
    case Succeeded = 'succeeded';
    case Failed = 'failed';
    case Incomplete = 'incomplete';
}
