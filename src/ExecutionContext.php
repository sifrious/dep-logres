<?php

declare(strict_types=1);

namespace Sifrious\Logres;

final readonly class ExecutionContext
{
    public function __construct(
        public ?string $projectReference,
        public ?string $repositoryReference = null,
    ) {}
}
