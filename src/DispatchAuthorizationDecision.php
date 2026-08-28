<?php

declare(strict_types=1);

namespace Sifrious\Logres;

use InvalidArgumentException;

final readonly class DispatchAuthorizationDecision
{
    public array $failures;

    public function __construct(
        public bool $allowed,
        public ?DispatchAuthorizationSnapshot $snapshot,
        array $failures,
    ) {
        $this->failures = array_values($failures);

        if (($allowed && ($snapshot === null || $this->failures !== [])) || (! $allowed && ($snapshot !== null || $this->failures === []))) {
            throw new InvalidArgumentException('Dispatch authorization decision, snapshot, and failures must agree.');
        }
    }
}
