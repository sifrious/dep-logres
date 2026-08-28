<?php

declare(strict_types=1);

namespace Sifrious\Logres;

interface ExecutionObserver
{
    public function contextResolved(TurnContext $context): void;

    public function processStarted(HarnessHandle $handle): void;

    public function stdout(string $chunk): void;

    public function stderr(string $chunk): void;

    public function artifact(ArtifactReference $artifact): void;
}
