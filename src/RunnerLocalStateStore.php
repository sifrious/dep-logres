<?php

declare(strict_types=1);

namespace Sifrious\Logres;

interface RunnerLocalStateStore
{
    public function find(string $executionKey): ?RunnerLocalRecord;

    /** Must durably persist before returning; implementations serialize concurrent writes. */
    public function save(RunnerLocalRecord $record): void;
}
