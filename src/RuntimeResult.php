<?php

declare(strict_types=1);

namespace Sifrious\Logres;

use InvalidArgumentException;

final readonly class RuntimeResult
{
    public function __construct(
        public RunnerTerminalStatus $status,
        public ?int $exitCode = null,
        public ?string $failureCategory = null,
        public ?string $failureDetail = null,
        public ?string $resultingRevision = null,
        public ?string $diffIdentity = null,
    )
    {
        if ($status === RunnerTerminalStatus::Success && $exitCode !== 0) {
            throw new InvalidArgumentException('A successful runtime result requires exit code zero.');
        }
        if ($status !== RunnerTerminalStatus::Success && $exitCode === 0) {
            throw new InvalidArgumentException('A non-successful runtime result cannot carry exit code zero.');
        }
        if (($resultingRevision === null) !== ($diffIdentity === null)) {
            throw new InvalidArgumentException('Wardrobe must report resulting revision and diff identity together.');
        }
    }
}
