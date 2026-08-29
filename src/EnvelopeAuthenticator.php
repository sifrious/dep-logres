<?php

declare(strict_types=1);

namespace Sifrious\Logres;

interface EnvelopeAuthenticator
{
    public function authenticates(ExecutionEnvelope $envelope): bool;
}
