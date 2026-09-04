<?php

declare(strict_types=1);

namespace Sifrious\Logres;

enum ArtifactAccessClassification: string
{
    case Public = 'public';
    case Internal = 'internal';
    case Restricted = 'restricted';
    case Secret = 'secret';
}
