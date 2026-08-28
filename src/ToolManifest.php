<?php

declare(strict_types=1);

namespace Sifrious\Logres;

use InvalidArgumentException;

final readonly class ToolManifest
{
    public function __construct(
        public string $id,
        public string $capability,
        public array $inputSchema,
        public array $authorization,
        public string $binding,
    ) {
        if (preg_match('/^[a-z][a-z0-9._-]*$/', $this->id) !== 1 || trim($this->capability) === '' || trim($this->binding) === '') {
            throw new InvalidArgumentException('A tool requires a stable ID, capability, and binding.');
        }

        foreach (['operators', 'workspaces', 'capabilities'] as $key) {
            if (! isset($this->authorization[$key]) || ! is_array($this->authorization[$key]) || $this->authorization[$key] === []) {
                throw new InvalidArgumentException("Tool authorization field {$key} requires a nonempty list.");
            }
        }
    }

    public static function fromArray(array $manifest): self
    {
        foreach (['id', 'capability', 'input_schema', 'authorization', 'binding'] as $key) {
            if (! array_key_exists($key, $manifest)) {
                throw new InvalidArgumentException("Tool manifest field {$key} is required.");
            }
        }

        if (! is_array($manifest['input_schema']) || ! is_array($manifest['authorization'])) {
            throw new InvalidArgumentException('Tool input schema and authorization must be arrays.');
        }

        return new self(
            (string) $manifest['id'],
            (string) $manifest['capability'],
            $manifest['input_schema'],
            $manifest['authorization'],
            (string) $manifest['binding'],
        );
    }
}
