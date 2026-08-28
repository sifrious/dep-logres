<?php

declare(strict_types=1);

namespace Sifrious\Logres;

use InvalidArgumentException;

final readonly class ProviderExecutionId
{
    public function __construct(
        public string $provider,
        public string $value,
    ) {
        if (preg_match('/^[a-z][a-z0-9._-]*$/', $provider) !== 1 || trim($value) === '') {
            throw new InvalidArgumentException('A provider execution identity requires a provider namespace and stable value.');
        }
    }

    public function canonical(): string
    {
        return "{$this->provider}:{$this->value}";
    }
}
