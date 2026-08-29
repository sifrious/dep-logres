<?php

declare(strict_types=1);

namespace Sifrious\Logres;

use InvalidArgumentException;

final readonly class RunResult
{
    /** @var list<RunEvidence> */
    public array $evidence;

    public function __construct(
        public RunStatus $status,
        public string $stdout = '',
        public string $stderr = '',
        public ?int $exitCode = null,
        public ?int $signal = null,
        public ?string $reason = null,
        array $evidence = [],
        public ?string $agentClaim = null,
        public ?string $observedOutcome = null,
        public ?RequiredVerificationOutcome $requiredVerification = null,
    ) {
        if (! $this->status->isTerminal()) {
            throw new InvalidArgumentException('A run result requires a terminal status.');
        }

        if ($this->status === RunStatus::Succeeded && ($this->exitCode !== 0 || $this->signal !== null)) {
            throw new InvalidArgumentException('A successful run requires exit code zero and no signal.');
        }

        if ($this->status !== RunStatus::Succeeded && $this->exitCode === 0) {
            throw new InvalidArgumentException('A non-successful run cannot carry exit code zero.');
        }

        foreach ($evidence as $item) {
            if (! $item instanceof RunEvidence) {
                throw new InvalidArgumentException('Run result evidence must contain RunEvidence values.');
            }
        }
        $this->evidence = array_values($evidence);
    }

    public static function succeeded(string $stdout = '', string $stderr = '', array $evidence = [], ?string $agentClaim = null, ?string $observedOutcome = null): self
    {
        return new self(RunStatus::Succeeded, $stdout, $stderr, 0, evidence: $evidence, agentClaim: $agentClaim, observedOutcome: $observedOutcome);
    }

    public static function failed(string $stderr, ?int $exitCode = null, string $stdout = '', ?int $signal = null): self
    {
        return new self(RunStatus::Failed, $stdout, $stderr, $exitCode, $signal);
    }

    public static function timedOut(string $stdout = '', string $stderr = '', ?string $reason = null): self
    {
        return new self(RunStatus::TimedOut, $stdout, $stderr, null, null, $reason);
    }

    public static function cancelled(string $stdout = '', string $stderr = '', ?string $reason = null): self
    {
        return new self(RunStatus::Cancelled, $stdout, $stderr, null, null, $reason);
    }

    public static function providerError(string $stderr, ?string $reason = null): self
    {
        return new self(RunStatus::ProviderError, stderr: $stderr, reason: $reason);
    }

    public function withRequiredVerification(RequiredVerificationOutcome $outcome): self
    {
        $status = $this->status;
        $reason = $this->reason;
        $exitCode = $this->exitCode;

        if ($status === RunStatus::Succeeded && $outcome !== RequiredVerificationOutcome::Passed) {
            $status = RunStatus::Failed;
            $exitCode = null;
            $reason ??= 'Required verification did not pass.';
        }

        return new self(
            status: $status,
            stdout: $this->stdout,
            stderr: $this->stderr,
            exitCode: $exitCode,
            signal: $this->signal,
            reason: $reason,
            evidence: $this->evidence,
            agentClaim: $this->agentClaim,
            observedOutcome: $this->observedOutcome,
            requiredVerification: $outcome,
        );
    }
}
