<?php

declare(strict_types=1);

namespace Sifrious\Logres;

/** Best-effort historical propagation performed only after local durability. */
interface RunResultHistorian
{
    public function export(string $requestIdentity, RunResult $result): void;
}
