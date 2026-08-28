<?php

declare(strict_types=1);

namespace Sifrious\Logres;

final readonly class ToolAuthorizationContext
{
    public function __construct(
        public string $operator,
        public string $workspace,
        public array $capabilities,
    ) {}
}
