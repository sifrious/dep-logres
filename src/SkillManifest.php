<?php

declare(strict_types=1);

namespace Sifrious\Logres;

use InvalidArgumentException;

final readonly class SkillManifest
{
    public function __construct(
        public string $id,
        public array $inputs,
        public array $outputs,
        public array $dependencies,
        public string $lifecycle,
        public array $triggers,
        public array $exclusions,
        public array $gates,
        public array $completion,
        public array $resources = [],
        public bool $alwaysOn = false,
    ) {
        if (preg_match('/^[a-z][a-z0-9._-]*$/', $this->id) !== 1) {
            throw new InvalidArgumentException('A skill ID must be a stable lowercase identifier.');
        }

        if (trim($this->lifecycle) === '') {
            throw new InvalidArgumentException('A skill lifecycle is required.');
        }

        self::assertStringList($this->dependencies, 'dependencies');
        self::assertStringList($this->resources, 'resources');
    }

    public static function fromArray(array $manifest): self
    {
        $required = ['id', 'inputs', 'outputs', 'dependencies', 'lifecycle', 'triggers', 'exclusions', 'gates', 'completion'];

        foreach ($required as $key) {
            if (! array_key_exists($key, $manifest)) {
                throw new InvalidArgumentException("Skill manifest field {$key} is required.");
            }
        }

        foreach (['inputs', 'outputs', 'dependencies', 'triggers', 'exclusions', 'gates', 'completion'] as $key) {
            if (! is_array($manifest[$key])) {
                throw new InvalidArgumentException("Skill manifest field {$key} must be an array.");
            }
        }

        if (isset($manifest['resources']) && ! is_array($manifest['resources'])) {
            throw new InvalidArgumentException('Skill manifest field resources must be an array.');
        }

        return new self(
            (string) $manifest['id'],
            $manifest['inputs'],
            $manifest['outputs'],
            $manifest['dependencies'],
            (string) $manifest['lifecycle'],
            $manifest['triggers'],
            $manifest['exclusions'],
            $manifest['gates'],
            $manifest['completion'],
            $manifest['resources'] ?? [],
            (bool) ($manifest['always_on'] ?? false),
        );
    }

    private static function assertStringList(array $values, string $field): void
    {
        foreach ($values as $value) {
            if (! is_string($value) || trim($value) === '') {
                throw new InvalidArgumentException("Skill manifest field {$field} must contain nonempty strings.");
            }
        }

        if ($values !== array_values(array_unique($values))) {
            throw new InvalidArgumentException("Skill manifest field {$field} cannot contain duplicates.");
        }
    }
}
