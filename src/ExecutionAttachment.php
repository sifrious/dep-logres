<?php

declare(strict_types=1);

namespace Sifrious\Logres;

final readonly class ExecutionAttachment
{
    public function __construct(
        public string $reference,
        public string $name,
    ) {}
}
