<?php

declare(strict_types=1);

namespace Sifrious\Logres;

use InvalidArgumentException;

/**
 * A reference to a completed deliberation checkpoint owned outside Logres.
 */
final readonly class LoopCheckpoint
{
    public function __construct(
        public LoopCheckpointType $type,
        public string $decisionReference,
        public int $sequence,
    ) {
        if (trim($decisionReference) === '' || $sequence < 1) {
            throw new InvalidArgumentException('A Loop checkpoint requires a decision reference and positive sequence.');
        }
    }
}
