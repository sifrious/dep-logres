<?php

declare(strict_types=1);

namespace Sifrious\Logres;

final readonly class ExecutionConstraints
{
    public array $writablePaths;

    public function __construct(
        public int $timeoutSeconds,
        array $writablePaths = [],
    ) {
        $this->writablePaths = array_values($writablePaths);
    }
}
