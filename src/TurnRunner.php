<?php

declare(strict_types=1);

namespace Sifrious\Logres;

use InvalidArgumentException;

final readonly class TurnRunner
{
    public function __construct(
        private InvariantPreflight $invariantPreflight,
        private BeforeTurnPipeline $before,
        private AfterTurnPipeline $after,
    ) {}

    public function run(
        RunRequest $request,
        TurnContext $context,
        HarnessInterface $harness,
        ExecutionObserver $observer,
    ): RunResult {
        if ($request->harnessId !== $harness->id()) {
            throw new InvalidArgumentException("Run request targets {$request->harnessId}, not {$harness->id()}.");
        }

        $context = $this->invariantPreflight->process($request, $context);
        $context = $this->before->process($request, $context);
        $observer->contextResolved($context);

        $handle = $harness->start($request, $context, $observer);
        $observer->processStarted($handle);

        do {
            $status = $harness->status($handle, $observer);
        } while (! $status->status->isTerminal());

        return $this->after->process($request, $context, $status->terminalResult());
    }
}
