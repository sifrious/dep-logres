<?php

declare(strict_types=1);

namespace Sifrious\Logres;

final readonly class OutboundRunnerCycleResult
{
    private function __construct(
        public OutboundRunnerCycleStatus $status,
        public ?int $retryAfterSeconds = null,
        public ?RunnerLeaseAcknowledgementResult $acknowledgement = null,
        public ?RunnerExecutionOutcome $execution = null,
        public ?RunnerTerminalResultReceipt $terminalReceipt = null,
    ) {}

    public static function noWork(int $retryAfterSeconds): self
    {
        return new self(OutboundRunnerCycleStatus::NoWork, $retryAfterSeconds);
    }

    public static function rejectedAck(RunnerLeaseAcknowledgementResult $acknowledgement): self
    {
        return new self(OutboundRunnerCycleStatus::RejectedAck, acknowledgement: $acknowledgement);
    }

    public static function completed(
        RunnerLeaseAcknowledgementResult $acknowledgement,
        RunnerExecutionOutcome $execution,
        ?RunnerTerminalResultReceipt $terminalReceipt,
    ): self {
        return new self(
            OutboundRunnerCycleStatus::Completed,
            acknowledgement: $acknowledgement,
            execution: $execution,
            terminalReceipt: $terminalReceipt,
        );
    }

    public static function reportRetry(
        RunnerLeaseAcknowledgementResult $acknowledgement,
        RunnerExecutionOutcome $execution,
        RunnerTerminalResultReceipt $terminalReceipt,
    ): self {
        return new self(
            OutboundRunnerCycleStatus::ReportRetry,
            acknowledgement: $acknowledgement,
            execution: $execution,
            terminalReceipt: $terminalReceipt,
        );
    }
}
