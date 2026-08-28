<?php

declare(strict_types=1);

namespace Sifrious\Logres;

enum DeliveryChannel: string
{
    case Web = 'web';
    case Cli = 'cli';
    case Api = 'api';
}
