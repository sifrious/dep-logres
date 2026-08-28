<?php

declare(strict_types=1);

namespace Sifrious\Logres;

use InvalidArgumentException;

final readonly class HarnessProbe
{
    public function __construct(
        public bool $available,
        public ?EnvironmentSnapshot $environment = null,
        public ?string $reason = null,
    ) {
        if ($this->available && $this->environment === null) {
            throw new InvalidArgumentException('An available harness probe requires an environment snapshot.');
        }

        if (! $this->available && trim((string) $this->reason) === '') {
            throw new InvalidArgumentException('An unavailable harness probe requires a reason.');
        }
    }

    public static function available(EnvironmentSnapshot $environment): self
    {
        return new self(true, $environment);
    }

    public static function unavailable(string $reason): self
    {
        return new self(false, null, $reason);
    }
}
