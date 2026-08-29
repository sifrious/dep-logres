<?php

declare(strict_types=1);

namespace Sifrious\Logres;

use DateTimeImmutable;

interface RunnerLifecycle
{
    public function permits(ExecutionEnvelope $envelope, RunnerIdentity $runner, DateTimeImmutable $now): bool;

    public function cancellationRequested(ExecutionEnvelope $envelope): bool;
}
