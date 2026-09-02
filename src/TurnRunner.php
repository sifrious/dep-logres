<?php

declare(strict_types=1);

namespace Sifrious\Logres;

use InvalidArgumentException;
use Throwable;

final readonly class TurnRunner
{
    public function __construct(
        private InvariantPreflight $invariantPreflight,
        private BeforeTurnPipeline $before,
        private InvariantFinalization $invariantFinalization,
        private AfterTurnPipeline $after,
        private ?RunResultStore $results = null,
        private ?RunResultHistorian $historian = null,
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

        $durable = $this->results?->find($request->identity());
        if ($durable !== null) {
            return $durable;
        }

        // This is the hard gate. A failure escapes before any harness method is called.
        $context = $this->invariantPreflight->process($request, $context);
        $context = $this->before->process($request, $context);
        $observer->contextResolved($context);

        try {
            $handle = $harness->start($request, $context, $observer);
            $observer->processStarted($handle);

            do {
                $status = $harness->status($handle, $observer);
            } while (! $status->status->isTerminal());

            $providerResult = $status->terminalResult();
        } catch (Throwable $failure) {
            $providerResult = RunResult::providerError($failure->getMessage(), $failure::class);
        }

        try {
            $result = $this->invariantFinalization->process($request, $context, $providerResult);
            $result = $this->after->process($request, $context, $result);
            $this->invariantFinalization->assertCanonical($result);
        } catch (Throwable $failure) {
            $result = $providerResult->finalizationIncomplete($failure::class.': '.$failure->getMessage());
        }

        $this->results?->save($request->identity(), $result);

        try {
            $this->historian?->export($request->identity(), $result);
        } catch (Throwable) {
            // The durable local result is authoritative; historical export may retry elsewhere.
        }

        return $result;
    }
}
