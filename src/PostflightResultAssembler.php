<?php
declare(strict_types=1);
namespace Sifrious\Logres;
final readonly class PostflightResultAssembler
{
    public function assemble(RunResult $providerResult, PostflightReport $report): RunResult
    {
        return new RunResult(
            status: $providerResult->status,
            stdout: $providerResult->stdout,
            stderr: $providerResult->stderr,
            exitCode: $providerResult->exitCode,
            signal: $providerResult->signal,
            reason: $providerResult->reason,
            evidence: [...$providerResult->evidence, ...$report->evidence],
            agentClaim: $providerResult->agentClaim,
            observedOutcome: $report->observedOutcome,
        );
    }
}
