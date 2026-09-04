<?php

declare(strict_types=1);

namespace Sifrious\Logres;

interface RunnerWorkPoller
{
    public function poll(RunnerPollRequest $request): RunnerPollResponse;
}
