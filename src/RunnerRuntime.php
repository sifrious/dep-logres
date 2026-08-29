<?php

declare(strict_types=1);

namespace Sifrious\Logres;

/** Host adapter for Wardrobe; implementations must delegate to Wardrobe and never dispatch providers here. */
interface RunnerRuntime
{
    /** @return list<string> */
    public function availableAdapters(): array;

    public function canInvoke(string $adapter, string $runtime): bool;

    public function invoke(RuntimeRequest $request, RunnerRuntimeObserver $observer): RuntimeResult;
}
