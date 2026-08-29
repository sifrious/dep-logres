<?php

declare(strict_types=1);

namespace Sifrious\Logres;

interface RunnerAuthorization
{
    public function authorizes(ExecutionEnvelope $envelope): bool;
}
