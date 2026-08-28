<?php

declare(strict_types=1);

namespace Sifrious\Logres;

final readonly class ExecutionRequestFailure
{
    public function __construct(
        public string $code,
        public string $field,
        public string $message,
    ) {}
}
