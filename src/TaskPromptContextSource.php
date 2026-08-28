<?php

declare(strict_types=1);

namespace Sifrious\Logres;

use InvalidArgumentException;

final readonly class TaskPromptContextSource
{
    public string $contentHash;

    public function __construct(
        public string $id,
        public string $kind,
        public string $label,
        public string $content,
    ) {
        if (trim($id) === '' || trim($kind) === '' || trim($label) === '') {
            throw new InvalidArgumentException('A context source requires an identity, kind, and label.');
        }

        $this->contentHash = hash('sha256', $content);
    }

    public function canonicalData(): array
    {
        return [
            'content' => $this->content,
            'content_hash' => $this->contentHash,
            'id' => $this->id,
            'kind' => $this->kind,
            'label' => $this->label,
        ];
    }
}
