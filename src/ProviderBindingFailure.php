<?php

declare(strict_types=1);

namespace Sifrious\Logres;

use InvalidArgumentException;

final readonly class ProviderBindingFailure
{
    public function __construct(
        public string $code,
        public string $message,
    ) {
        if (trim($code) === '' || trim($message) === '') {
            throw new InvalidArgumentException('A provider-binding failure requires a code and message.');
        }
    }
}
