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
        $this->recordReceipt($executionKey, $receipt, $now);

        return $receipt;
    }

    public function recordReceipt(
        string $executionKey,
        RunnerTerminalResultReceipt $receipt,
        DateTimeImmutable $now,
    ): void {
        if (! in_array($receipt->status, [RunnerTerminalResultDeliveryStatus::Accepted, RunnerTerminalResultDeliveryStatus::Duplicate], true)) {
            return;
        }

        $record = $this->localState->find($executionKey);
        if ($record === null || $record->terminalResult === null || $record->stage !== RunnerLocalStage::Reporting) {
            return;
        }
        if ($record->terminalResult->runId->value !== $receipt->result->runId->value
            || $record->terminalResult->attemptId->value !== $receipt->result->attemptId->value
            || $record->terminalResult->leaseId->value !== $receipt->result->leaseId->value
            || $record->terminalResult->executionIdentity?->canonicalIdentity() !== $receipt->result->executionIdentity?->canonicalIdentity()) {
            return;
        }

        $this->localState->save(new RunnerLocalRecord(
            $record->executionKey,
            $record->idempotencyIdentity,
            $record->envelopeFingerprint,
            RunnerLocalStage::Terminal,
            $now,
            $record->terminalResult,
        ));
    }
}
