<?php

declare(strict_types=1);

namespace Sifrious\Logres;

final readonly class RunnerAcceptance
{
    private function __construct(public bool $accepted, public ?RunnerRejectionReason $reason = null, public ?string $detail = null) {}

    public static function accepted(): self
    {
        return new self(true);
    }

    public static function rejected(RunnerRejectionReason $reason, string $detail): self
    {
        return new self(false, $reason, $detail);
    }
}
