<?php

declare(strict_types=1);

namespace Sifrious\Logres;

interface RunnerEventSink
{
    /** Delivery is at-least-once; sinks deduplicate by RunnerEvent::id. */
    public function emit(RunnerEvent $event): void;
}
