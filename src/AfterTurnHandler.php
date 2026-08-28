<?php

declare(strict_types=1);

namespace Sifrious\Logres;

interface AfterTurnHandler
{
    public function handle(RunRequest $request, TurnContext $context, RunResult $result): RunResult;
}
