<?php

declare(strict_types=1);

namespace Sifrious\Logres;

use DateTimeImmutable;
use InvalidArgumentException;
use LogicException;
use Sifrious\Elwin\Handoff\ResumableHandoff;
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
        private ?TurnCheckpointStore $checkpoints = null,
    ) {}

    public function run(
        RunRequest $request,
        TurnContext $context,
        HarnessInterface $harness,
        ExecutionObserver $observer,
    ): RunResult {
        return $this->execute($request, $context, $harness, $observer, true);
    }

    public function resume(
        RunRequest $request,
        ResumableHandoff $handoff,
        HumanInputAuthorization $authorization,
        DateTimeImmutable $now,
        HarnessInterface $harness,
        ExecutionObserver $observer,
    ): RunResult {
        if (! $authorization->allowed) {
            throw ExecutionStateRejected::because(ExecutionStateRejectionReason::InputResponseUnauthorized, $authorization->reason);
        }
        if (! $handoff->isResumableAt($now)) {
            throw ExecutionStateRejected::because(ExecutionStateRejectionReason::InputNotResumable, 'Elwin has not accepted a resumable response for this handoff.');
        }

        $checkpoint = $this->checkpoints?->find($handoff->resumeContext->checkpoint)
            ?? throw new LogicException('The durable Turn checkpoint was not found.');
        if (! $checkpoint->matches($request, $handoff)) {
            throw ExecutionStateRejected::because(ExecutionStateRejectionReason::InputQuestionConflict, 'The Elwin handoff does not match the paused Turn checkpoint.');
        }

        return $this->execute($request, $checkpoint->context, $harness, $observer, false);
    }

    private function execute(
        RunRequest $request,
        TurnContext $context,
        HarnessInterface $harness,
        ExecutionObserver $observer,
        bool $runCompletedHandlers,
    ): RunResult {
        if ($request->harnessId !== $harness->id()) {
            throw new InvalidArgumentException("Run request targets {$request->harnessId}, not {$harness->id()}.");
        }

        $durable = $this->results?->find($request->identity());
        if ($durable !== null) {
            return $durable;
        }

        if ($runCompletedHandlers) {
            // This is the hard gate. A failure escapes before any harness method is called.
            $context = $this->invariantPreflight->process($request, $context);
            $context = $this->before->process($request, $context);
            $observer->contextResolved($context);
        }

        try {
            $handle = $harness->start($request, $context, $observer);
            $observer->processStarted($handle);

            do {
                $status = $harness->status($handle, $observer);
            } while (! $status->status->isTerminal());

            $providerResult = $status->terminalResult();
        } catch (NeedsInput $pause) {
            $this->checkpoints?->save(TurnCheckpoint::paused($request, $pause, $context));
            throw $pause;
        } catch (Throwable $failure) {
            $providerResult = RunResult::providerError($failure->getMessage(), $failure::class);
        }

        try {
            $finalized = $this->invariantFinalization->process($request, $context, $providerResult);
            $result = $this->after->process($request, $context, $finalized);

            if ($result->status !== $finalized->status
                || $result->requiredVerification !== $finalized->requiredVerification) {
                throw new \LogicException('Optional after-turn handlers cannot replace canonical result disposition.');
            }

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
