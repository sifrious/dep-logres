<?php

declare(strict_types=1);

namespace Sifrious\Logres;

use InvalidArgumentException;

/**
 * Points at an immutable Orbis-owned handoff without copying its prompt model.
 */
final readonly class LoopHandoffReference
{
    public function __construct(
        public LoopHandoffType $type,
        public string $artifactReference,
        public string $originReference,
        public string $contentHash,
        public ?TaskId $taskId = null,
    ) {
        if (trim($artifactReference) === '' || trim($originReference) === '') {
            throw new InvalidArgumentException('A Loop handoff requires artifact and origin references.');
        }

        if (preg_match('/^[a-f0-9]{64}$/', $contentHash) !== 1) {
            throw new InvalidArgumentException('A Loop handoff requires a SHA-256 content identity.');
        }

        if (($type === LoopHandoffType::Ticket) !== ($taskId !== null)) {
            throw new InvalidArgumentException('Ticket handoffs require a task; phase handoffs cannot identify one.');
        }
    }

    public function idempotencyIdentity(): string
    {
        return 'handoff:'.hash('sha256', implode("\0", [
            $this->type->value,
            $this->artifactReference,
            $this->originReference,
            $this->taskId?->value ?? '',
            $this->contentHash,
        ]));
    }
}
