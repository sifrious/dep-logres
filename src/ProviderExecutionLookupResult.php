<?php

declare(strict_types=1);

namespace Sifrious\Logres;

use InvalidArgumentException;

final readonly class ProviderExecutionLookupResult
{
    public function __construct(
        public ProviderLookupStatus $status,
        public ?ProviderAcknowledgement $acknowledgement = null,
        public ?string $reason = null,
    ) {
        if (($status === ProviderLookupStatus::Found && ($acknowledgement === null || $reason !== null))
            || ($status !== ProviderLookupStatus::Found && ($acknowledgement !== null || trim((string) $reason) === ''))) {
            throw new InvalidArgumentException('Provider lookup status, acknowledgement, and reason must agree.');
        }
    }
}
