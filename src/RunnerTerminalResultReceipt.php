<?php

declare(strict_types=1);

namespace Sifrious\Logres;

final readonly class RunnerTerminalResultReceipt
{
    public function __construct(
        public RunnerTerminalResultDeliveryStatus $status,
        public RunnerTerminalResult $result,
        public ?string $detail = null,
    ) {}
}
