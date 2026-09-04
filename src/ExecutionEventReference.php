<?php

declare(strict_types=1);

namespace Sifrious\Logres;

interface ExecutionEventReference
{
    /** @return array<string, mixed> */
    public function toArray(): array;
}
