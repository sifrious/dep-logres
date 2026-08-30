<?php

declare(strict_types=1);

namespace Sifrious\Logres;

use InvalidArgumentException;

final readonly class PreDispatchValidationFailure
{
    public function __construct(
        public string $code,
        public string $message,
        public string $failedAt,
    ) {
        if (trim($code) === ''
            || trim($message) === ''
            || preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d+)?Z$/', $failedAt) !== 1) {
            throw new InvalidArgumentException('A pre-dispatch validation failure requires a code, message, and explicit UTC timestamp.');
        }
    }
}
