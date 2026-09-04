<?php

declare(strict_types=1);

namespace Sifrious\Logres;

use InvalidArgumentException;

final readonly class TestExecutionReference implements ExecutionEventReference
{
    public function __construct(
        public string $suite,
        public string $name,
    ) {
        if (trim($this->suite) === '' || trim($this->name) === '') {
            throw new InvalidArgumentException('Test references require suite and name.');
        }
    }

    public function toArray(): array
    {
        return [
            'kind' => 'test_execution',
            'suite' => $this->suite,
            'name' => $this->name,
        ];
    }
}
