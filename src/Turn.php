<?php

declare(strict_types=1);

namespace Sifrious\Logres;

use InvalidArgumentException;

final readonly class Turn
{
    public function __construct(
        public string $prompt,
        public array $references = [],
    ) {
        if (trim($this->prompt) === '') {
            throw new InvalidArgumentException('A turn prompt cannot be blank.');
        }
    }
}
