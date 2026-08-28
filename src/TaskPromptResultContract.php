<?php

declare(strict_types=1);

namespace Sifrious\Logres;

use InvalidArgumentException;

final readonly class TaskPromptResultContract
{
    public array $requiredFields;

    public function __construct(
        public string $format,
        array $requiredFields,
    ) {
        if (trim($format) === '' || $requiredFields === []) {
            throw new InvalidArgumentException('A result contract requires a format and at least one required field.');
        }

        $this->requiredFields = array_values($requiredFields);
    }

    public function canonicalData(): array
    {
        return ['format' => $this->format, 'required_fields' => $this->requiredFields];
    }
}
