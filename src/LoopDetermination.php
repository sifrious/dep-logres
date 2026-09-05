<?php

declare(strict_types=1);

namespace Sifrious\Logres;

use InvalidArgumentException;

final readonly class LoopDetermination
{
    public function __construct(
        public LoopDisposition $disposition,
        public string $reason,
        public ?TaskId $taskId = null,
        public ?string $decisionReference = null,
    ) {
        if (trim($reason) === '') {
            throw new InvalidArgumentException('A Loop determination requires an explicit reason.');
        }

        if ($disposition === LoopDisposition::Rework && $taskId === null) {
            throw new InvalidArgumentException('Rework must identify the owning task.');
        }
    }
}
