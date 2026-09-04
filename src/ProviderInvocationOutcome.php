<?php

declare(strict_types=1);

namespace Sifrious\Logres;

final readonly class ProviderInvocationOutcome
{
    public function __construct(
        public ProviderInvocationStatus $status,
        public Run $run,
        public ProviderInvocationRecord $record,
    ) {}
}
