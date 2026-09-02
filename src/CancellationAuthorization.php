<?php

declare(strict_types=1);

namespace Sifrious\Logres;

final readonly class CancellationAuthorization
{
    private function __construct(public bool $allowed, public string $reason) {}

    public static function allow(): self
    {
        return new self(true, 'authorized');
    }

    public static function deny(string $reason): self
    {
        return new self(false, trim($reason) === '' ? 'unauthorized' : $reason);
    }
}
