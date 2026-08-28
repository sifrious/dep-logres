<?php

declare(strict_types=1);

namespace Sifrious\Logres;

use InvalidArgumentException;

final readonly class AfterTurnPipeline
{
    private array $handlers;

    public function __construct(iterable $handlers = [])
    {
        $resolved = [];

        foreach ($handlers as $handler) {
            if (! $handler instanceof AfterTurnHandler) {
                throw new InvalidArgumentException('Every after-turn handler must implement AfterTurnHandler.');
            }

            $resolved[] = $handler;
        }

        $this->handlers = $resolved;
    }

    public function process(RunRequest $request, TurnContext $context, RunResult $result): RunResult
    {
        foreach ($this->handlers as $handler) {
            $result = $handler->handle($request, $context, $result);
        }

        return $result;
    }
}
