<?php

declare(strict_types=1);

namespace Sifrious\Logres;

final readonly class RunnerLocalReservation
{
    public function __construct(public bool $acquired, public RunnerLocalRecord $record) {}
}
