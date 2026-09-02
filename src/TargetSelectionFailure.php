<?php

declare(strict_types=1);

namespace Sifrious\Logres;

use InvalidArgumentException;

final readonly class TargetSelectionFailure
{
    public function __construct(
        public string $code,
        public string $message,
        public array $candidateEvaluations = [],
    ) {
        if (trim($code) === '' || trim($message) === '') {
            throw new InvalidArgumentException('A target selection failure requires a code and message.');
        }
    }
}
