<?php

declare(strict_types=1);

namespace Sifrious\Logres;

enum LeaseStatus: string
{
    case Active = 'active';
    case Released = 'released';
    case Expired = 'expired';
}
