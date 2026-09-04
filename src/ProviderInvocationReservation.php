<?php

declare(strict_types=1);

namespace Sifrious\Logres;

final readonly class ProviderInvocationReservation
{
    public function __construct(public bool $acquired, public ProviderInvocationRecord $record) {}
}
