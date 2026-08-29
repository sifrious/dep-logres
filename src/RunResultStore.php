<?php

declare(strict_types=1);

namespace Sifrious\Logres;

/** Durable local authority for terminal execution results. */
interface RunResultStore
{
    public function find(string $requestIdentity): ?RunResult;

    public function save(string $requestIdentity, RunResult $result): void;
}
