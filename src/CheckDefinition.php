<?php

declare(strict_types=1);

namespace Sifrious\Logres;

use InvalidArgumentException;

final readonly class CheckDefinition
{
    /** @param array<string, mixed> $referenceCriteria */
    public function __construct(
        public string $id,
        public string $name,
        public ExecutionEventType $eventType,
        public bool $required = true,
        public bool $enabled = true,
        public array $referenceCriteria = [],
    ) {
        if (trim($this->id) === '' || trim($this->name) === '') {
            throw new InvalidArgumentException('Check definitions require identity and name.');
        }

        foreach ($this->referenceCriteria as $key => $value) {
            if ($key === '' || ! is_scalar($value)) {
                throw new InvalidArgumentException('Check definition reference criteria must be keyed scalar values.');
            }
        }
    }

    public function matches(ExecutionEvent $event): bool
    {
        if ($event->type !== $this->eventType->value) {
            return false;
        }

        if ($this->referenceCriteria === []) {
            return true;
        }

        $reference = $event->payload['reference'] ?? null;
        if (! is_array($reference)) {
            return false;
        }

        foreach ($this->referenceCriteria as $key => $expected) {
            if (! array_key_exists($key, $reference) || (string) $reference[$key] !== (string) $expected) {
                return false;
            }
        }

        return true;
    }
}
