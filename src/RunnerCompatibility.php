<?php

declare(strict_types=1);

namespace Sifrious\Logres;

final readonly class RunnerCompatibility
{
    /** @param list<RunnerCompatibilityFailure> $failures */
    public function __construct(public array $failures) {}

    public function compatible(): bool
    {
        return $this->failures === [];
    }
}
