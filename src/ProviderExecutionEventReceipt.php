<?php

declare(strict_types=1);

namespace Sifrious\Logres;

final readonly class ProviderExecutionEventReceipt
{
    public function __construct(
        public ProviderExecutionEventStatus $status,
        public ProviderExecutionEventLog $log,
        public ?ExecutionEvent $event = null,
    ) {}
}
