<?php

declare(strict_types=1);

namespace Sifrious\Logres;

interface RunnerLeaseStore
{
    public function create(RunnerLease $lease): void;
    public function save(RunnerLease $lease): void;
    public function find(string $id): ?RunnerLease;
    public function activeForRun(RunId $runId): ?RunnerLease;
}
