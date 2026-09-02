<?php

declare(strict_types=1);

namespace Sifrious\Logres;

use InvalidArgumentException;

final readonly class PlatformIdentity
{
    public function __construct(public string $operatingSystem, public string $architecture)
    {
        if (trim($operatingSystem) === '' || trim($architecture) === '') {
            throw new InvalidArgumentException('Platform identity requires an operating system and architecture.');
        }
    }
}
