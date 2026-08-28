<?php

declare(strict_types=1);

namespace Sifrious\Logres;

use InvalidArgumentException;
use LogicException;

final readonly class HarnessStatus
{
    public function __construct(
        public RunStatus $status,
        public ?RunResult $result = null,
    ) {
        if ($this->status->isTerminal() !== ($this->result !== null)) {
            throw new InvalidArgumentException('A terminal harness status requires one result and a nonterminal status cannot carry one.');
        }

        if ($this->result !== null && $this->result->status !== $this->status) {
            throw new InvalidArgumentException('Harness status and result status must match.');
        }
    }

    public static function active(RunStatus $status = RunStatus::Running): self
    {
        return new self($status);
    }

    public static function terminal(RunResult $result): self
    {
        return new self($result->status, $result);
    }

    public function terminalResult(): RunResult
    {
        return $this->result ?? throw new LogicException('The harness status is not terminal.');
    }
}
