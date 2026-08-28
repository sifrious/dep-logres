<?php

declare(strict_types=1);

namespace Sifrious\Logres;

use InvalidArgumentException;

final readonly class TaskPromptTool
{
    public function __construct(
        public string $id,
        public string $capability,
    ) {
        if (trim($id) === '' || trim($capability) === '') {
            throw new InvalidArgumentException('A selected tool requires an identity and capability.');
        }
    }

    public function canonicalData(): array
    {
        return ['capability' => $this->capability, 'id' => $this->id];
    }
}
