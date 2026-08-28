<?php

declare(strict_types=1);

namespace Sifrious\Logres;

use InvalidArgumentException;

final readonly class BeforeTurnPipeline
{
    private array $handlers;

    public function __construct(iterable $handlers = [])
    {
        $resolved = [];

        foreach ($handlers as $handler) {
            if (! $handler instanceof BeforeTurnHandler) {
                throw new InvalidArgumentException('Every before-turn handler must implement BeforeTurnHandler.');
            }

            $resolved[] = $handler;
        }

        $this->handlers = $resolved;
    }

    public function process(RunRequest $request, TurnContext $context): TurnContext
    {
        foreach ($this->handlers as $handler) {
            $context = $handler->handle($request, $context);
        }

        return $context;
    }
}
