<?php

declare(strict_types=1);

namespace Sifrious\Logres;

use InvalidArgumentException;

final readonly class ProviderBindingResult
{
    public function __construct(
        public ProviderBindingOutcome $outcome,
        public Run $run,
        public ?ProviderBindingFailure $failure = null,
    ) {
        if (($outcome === ProviderBindingOutcome::Conflict || $outcome === ProviderBindingOutcome::ReconciliationRequired) !== ($failure !== null)) {
            throw new InvalidArgumentException('Provider-binding outcome and failure must agree.');
        }
    }
}
