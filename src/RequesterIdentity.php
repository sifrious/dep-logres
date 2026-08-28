<?php

declare(strict_types=1);

namespace Sifrious\Logres;

final readonly class RequesterIdentity
{
    public function __construct(
        public string $reference,
        public string $displayName,
    ) {}
}
