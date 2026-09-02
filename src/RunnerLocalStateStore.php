<?php

declare(strict_types=1);

namespace Sifrious\Logres;

interface RunnerLocalStateStore
{
    public function find(string $executionKey): ?RunnerLocalRecord;

    /** Atomically reserves both execution key and idempotency identity, or returns the existing winner. */
    public function reserve(RunnerLocalRecord $record): RunnerLocalReservation;

    /** Must durably persist before returning; implementations serialize concurrent writes. */
    public function save(RunnerLocalRecord $record): void;
}
