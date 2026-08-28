<?php

declare(strict_types=1);

namespace Sifrious\Logres;

interface BeforeTurnHandler
{
    public function handle(RunRequest $request, TurnContext $context): TurnContext;
}
