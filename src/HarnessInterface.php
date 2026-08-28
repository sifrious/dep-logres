<?php

declare(strict_types=1);

namespace Sifrious\Logres;

interface HarnessInterface
{
    public function id(): string;

    public function capabilities(): HarnessCapability;

    public function probe(): HarnessProbe;

    public function start(RunRequest $request, TurnContext $context, ExecutionObserver $observer): HarnessHandle;

    public function status(HarnessHandle $handle, ExecutionObserver $observer): HarnessStatus;

    public function cancel(HarnessHandle $handle): void;
}
