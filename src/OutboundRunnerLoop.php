<?php

declare(strict_types=1);

namespace Sifrious\Logres;

use LogicException;

/**
 * Executes one transport-neutral outbound poll, acknowledgement, execution,
 * and terminal-delivery cycle.
 */
final readonly class OutboundRunnerLoop
{
    public function __construct(
        private RunnerWorkPoller $poller,
        private RunnerLeaseAcknowledger $acknowledger,
        private ExecutionRunner $executionRunner,
        private RunnerTerminalResultSink $terminalSink,
        private RunnerTerminalReconciler $terminalReconciler,
    ) {}

    public function run(RunnerPollRequest $request): OutboundRunnerCycleResult
    {
        $poll = $this->poller->poll($request);
        if ($poll->status === RunnerPollResponseStatus::NoWork) {
            if ($poll->retryAfterSeconds === null) {
                throw new LogicException('A no-work poll response must carry a retry delay.');
            }

            return OutboundRunnerCycleResult::noWork($poll->retryAfterSeconds);
        }
        if ($poll->envelope === null || $poll->lease === null) {
            throw new LogicException('A lease poll response must carry an envelope and lease.');
        }

        $envelope = $poll->envelope;
        $acknowledgement = $this->acknowledger->acknowledge(new RunnerLeaseAcknowledgement(
            $poll->lease->id,
            $request->runnerId,
            self::acknowledgementId($envelope),
            $request->observedAt,
        ));
        if (in_array($acknowledgement->status, [
            RunnerLeaseAcknowledgementStatus::Rejected,
            RunnerLeaseAcknowledgementStatus::Conflict,
        ], true)) {
            return OutboundRunnerCycleResult::rejectedAck($acknowledgement);
        }

        $execution = $this->executionRunner->execute($envelope->toArray(), $request->observedAt);
        if ($execution->terminalResult === null) {
            return OutboundRunnerCycleResult::completed($acknowledgement, $execution, null);
        }

        $receipt = $this->terminalSink->report($execution->terminalResult);
        $this->terminalReconciler->recordReceipt(
            RunnerLocalRecord::key($envelope),
            $receipt,
            $request->observedAt,
        );

        if ($receipt->status === RunnerTerminalResultDeliveryStatus::Retry) {
            return OutboundRunnerCycleResult::reportRetry($acknowledgement, $execution, $receipt);
        }

        return OutboundRunnerCycleResult::completed($acknowledgement, $execution, $receipt);
    }

    /**
     * Stable rule: runner-ack:sha256:<hex> over run, attempt, and lease values,
     * joined in that order with a NUL byte.
     */
    public static function acknowledgementId(ExecutionEnvelope $envelope): string
    {
        return 'runner-ack:sha256:'.hash('sha256', implode("\0", [
            $envelope->runId->value,
            $envelope->attemptId->value,
            $envelope->leaseId->value,
        ]));
    }
}
