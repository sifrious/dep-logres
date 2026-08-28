<?php

declare(strict_types=1);

namespace Sifrious\Logres;

enum TaskStartAuthority: string
{
    case Manual = 'manual';
    case Automatic = 'automatic';
}
