<?php

declare(strict_types=1);

namespace Sifrious\Logres;

interface RunnerLeaseAcknowledger
{
    public function acknowledge(RunnerLeaseAcknowledgement $acknowledgement): RunnerLeaseAcknowledgementResult;
}
