<?php

declare(strict_types=1);

namespace Sifrious\Logres;

use InvalidArgumentException;

final readonly class RuntimeResult
{
    public function __construct(public RunnerTerminalStatus $status, public ?int $exitCode = null, public ?string $failureCategory = null, public ?string $failureDetail = null)
    {
        if ($status === RunnerTerminalStatus::Success && $exitCode !== 0) {
            throw new InvalidArgumentException('A successful runtime result requires exit code zero.');
        }
        if ($status !== RunnerTerminalStatus::Success && $exitCode === 0) {
            throw new InvalidArgumentException('A non-successful runtime result cannot carry exit code zero.');
        }
    }
}
