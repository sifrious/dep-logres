<?php

declare(strict_types=1);

namespace Sifrious\Logres;

interface RunExecutionRecordStore
{
    public function find(RunId $runId): ?RunExecutionRecord;
    public function save(RunExecutionRecord $record): void;
}
