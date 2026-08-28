<?php

declare(strict_types=1);

namespace Sifrious\Logres;

enum ProviderLookupStatus: string
{
    case Found = 'found';
    case NotFound = 'not_found';
    case Unavailable = 'unavailable';
}
