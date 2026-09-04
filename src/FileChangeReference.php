<?php

declare(strict_types=1);

namespace Sifrious\Logres;

use InvalidArgumentException;

final readonly class FileChangeReference implements ExecutionEventReference
{
    public function __construct(
        public string $path,
        public string $changeType,
    ) {
        if (trim($this->path) === '' || trim($this->changeType) === '') {
            throw new InvalidArgumentException('File-change references require path and change type.');
        }
    }

    public function toArray(): array
    {
        return [
            'kind' => 'file_change',
            'path' => $this->path,
            'change_type' => $this->changeType,
        ];
    }
}
