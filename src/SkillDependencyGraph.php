<?php

declare(strict_types=1);

namespace Sifrious\Logres;

use InvalidArgumentException;

final readonly class SkillDependencyGraph
{
    private array $dependencies;
    private array $unresolved;

    public function __construct(iterable $manifests)
    {
        $byId = [];

        foreach ($manifests as $manifest) {
            if (! $manifest instanceof SkillManifest) {
                throw new InvalidArgumentException('A skill graph accepts only SkillManifest values.');
            }

            if (isset($byId[$manifest->id])) {
                throw new InvalidArgumentException("Skill {$manifest->id} appears more than once in the graph.");
            }

            $byId[$manifest->id] = $manifest;
        }

        ksort($byId);
        $dependencies = [];
        $unresolved = [];

        foreach ($byId as $id => $manifest) {
            $declared = $manifest->dependencies;
            sort($declared);
            $dependencies[$id] = $declared;
            $missing = array_values(array_filter($declared, static fn (string $dependency): bool => ! isset($byId[$dependency])));

            if ($missing !== []) {
                $unresolved[$id] = $missing;
            }
        }

        $this->dependencies = $dependencies;
        $this->unresolved = $unresolved;
    }

    public function dependenciesOf(string $skillId): array
    {
        return $this->dependencies[$skillId] ?? throw new InvalidArgumentException("Skill {$skillId} is not in the graph.");
    }

    public function unresolved(): array
    {
        return $this->unresolved;
    }
}
