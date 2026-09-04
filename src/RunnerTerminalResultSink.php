<?php

declare(strict_types=1);

namespace Sifrious\Logres;

interface RunnerTerminalResultSink
{
    public function report(RunnerTerminalResult $result): RunnerTerminalResultReceipt;
}
