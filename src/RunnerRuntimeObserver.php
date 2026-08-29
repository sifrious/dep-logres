<?php

declare(strict_types=1);

namespace Sifrious\Logres;

interface RunnerRuntimeObserver
{
    public function event(RunnerEventType $type, array $payload = []): void;

    public function cancellationRequested(): bool;
}
