<?php

declare(strict_types=1);

namespace Sifrious\Logres;

use InvalidArgumentException;

final readonly class ProviderDispatchResult
{
    public function __construct(
        public ProviderInvocationStatus $status,
        public ?ProviderAcknowledgement $acknowledgement = null,
        public ?string $reason = null,
    ) {
        if (! in_array($status, [ProviderInvocationStatus::Accepted, ProviderInvocationStatus::Rejected, ProviderInvocationStatus::Unavailable, ProviderInvocationStatus::AcknowledgementUncertain, ProviderInvocationStatus::BindingConflict], true)) {
            throw new InvalidArgumentException('A provider dispatch result must be a terminal dispatch observation.');
        }
        $acknowledgementStatus = in_array($status, [ProviderInvocationStatus::Accepted, ProviderInvocationStatus::BindingConflict], true);
        if ($acknowledgementStatus !== ($acknowledgement !== null)
            || ($status === ProviderInvocationStatus::Accepted && $reason !== null)
            || (in_array($status, [ProviderInvocationStatus::Rejected, ProviderInvocationStatus::Unavailable, ProviderInvocationStatus::AcknowledgementUncertain, ProviderInvocationStatus::BindingConflict], true) && trim((string) $reason) === '')) {
            throw new InvalidArgumentException('Provider dispatch status, acknowledgement, and reason must agree.');
        }
    }

    public static function accepted(ProviderAcknowledgement $acknowledgement): self
    {
        return new self(ProviderInvocationStatus::Accepted, $acknowledgement);
    }

    public static function rejected(string $reason): self
    {
        return new self(ProviderInvocationStatus::Rejected, reason: $reason);
    }

    public static function unavailable(string $reason): self
    {
        return new self(ProviderInvocationStatus::Unavailable, reason: $reason);
    }

    public static function acknowledgementUncertain(string $reason): self
    {
        return new self(ProviderInvocationStatus::AcknowledgementUncertain, reason: $reason);
    }

    public static function bindingConflict(ProviderAcknowledgement $acknowledgement, string $reason): self
    {
        return new self(ProviderInvocationStatus::BindingConflict, $acknowledgement, $reason);
    }
}
