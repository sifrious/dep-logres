<?php

declare(strict_types=1);

namespace Sifrious\Logres;

use DateTimeImmutable;

final readonly class RunnerTerminalReconciler
{
    public function __construct(
        private RunnerLocalStateStore $localState,
        private RunnerTerminalResultSink $sink,
    ) {}

    public function reconcile(string $executionKey, DateTimeImmutable $now): ?RunnerTerminalResultReceipt
    {
        $record = $this->localState->find($executionKey);
        if ($record === null || $record->terminalResult === null) {
            return null;
        }

        if ($record->stage === RunnerLocalStage::Terminal) {
            return new RunnerTerminalResultReceipt(RunnerTerminalResultDeliveryStatus::Duplicate, $record->terminalResult, 'already_terminal_local');
        }
        if ($record->stage !== RunnerLocalStage::Reporting) {
            return null;
        }

        $receipt = $this->sink->report($record->terminalResult);
        if (in_array($receipt->status, [RunnerTerminalResultDeliveryStatus::Accepted, RunnerTerminalResultDeliveryStatus::Duplicate], true)) {
            $this->localState->save(new RunnerLocalRecord(
                $record->executionKey,
                $record->idempotencyIdentity,
                $record->envelopeFingerprint,
                RunnerLocalStage::Terminal,
                $now,
                $record->terminalResult,
            ));
        }

        return $receipt;
    }
}
