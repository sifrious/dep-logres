<?php

declare(strict_types=1);

namespace Sifrious\Logres;

use InvalidArgumentException;

/** Host-supplied authority to apply an Elwin handoff to Logres execution state. */
final readonly class HumanInputAuthorization
{
    private function __construct(
        public bool $allowed,
        public string $reason,
    ) {
        if (trim($this->reason) === '') {
            throw new InvalidArgumentException('A human-input authorization decision requires a reason.');
        }
    }

    public static function allow(string $reason = 'authorized'): self
    {
        return new self(true, $reason);
    }

    public static function deny(string $reason): self
    {
        return new self(false, $reason);
    }
}
