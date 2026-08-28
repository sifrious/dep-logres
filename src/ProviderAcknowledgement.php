<?php

declare(strict_types=1);

namespace Sifrious\Logres;

use InvalidArgumentException;

final readonly class ProviderAcknowledgement
{
    public function __construct(
        public ProviderExecutionId $providerExecutionId,
        public ExecutionTargetId $targetId,
        public string $receivedAt,
    ) {
        if (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d+)?Z$/', $receivedAt) !== 1) {
            throw new InvalidArgumentException('A provider acknowledgement requires an explicit UTC timestamp.');
        }
    }
}
