<?php

declare(strict_types=1);

namespace Sifrious\Logres;

final readonly class RunnerExecutionOutcome
{
    private function __construct(public RunnerAcceptance $acceptance, public ?RunnerTerminalResult $terminalResult = null) {}

    public static function rejected(RunnerRejectionReason $reason, string $detail, ?RunnerTerminalResult $terminal = null): self
    {
        return new self(RunnerAcceptance::rejected($reason, $detail), $terminal);
    }

    public static function completed(RunnerTerminalResult $terminal): self
    {
        return new self(RunnerAcceptance::accepted(), $terminal);
    }
}
