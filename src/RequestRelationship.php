<?php

declare(strict_types=1);

namespace Sifrious\Logres;

enum RequestRelationship: string
{
    case Original = 'original';
    case Correction = 'correction';
    case Child = 'child';
}
