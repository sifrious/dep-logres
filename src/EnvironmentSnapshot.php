<?php

declare(strict_types=1);

namespace Sifrious\Logres;

final readonly class EnvironmentSnapshot
{
    public function __construct(
        public string $operatingSystem,
        public string $applicationVersion,
        public ?string $harnessVersion,
        public ?string $executable,
        public array $capabilities = [],
    ) {}
}
