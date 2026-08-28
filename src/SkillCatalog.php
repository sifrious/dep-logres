<?php

declare(strict_types=1);

namespace Sifrious\Logres;

use InvalidArgumentException;

final class SkillCatalog
{
    public static function canonicalize(iterable $skills): SkillCatalogResult
    {
        $byLocation = [];
        $byId = [];
        $canonical = [];
        $conflicts = [];

        foreach ($skills as $skill) {
            if (! $skill instanceof InstalledSkill) {
                throw new InvalidArgumentException('A skill catalog accepts only InstalledSkill values.');
            }

            $location = $skill->canonicalLocation();

            if (isset($byLocation[$location])) {
                continue;
            }

            $byLocation[$location] = $skill;
            $id = $skill->manifest->id;

            if (isset($byId[$id]) && $byId[$id]->contentHash !== $skill->contentHash) {
                $conflicts[] = new SkillConflict(
                    $id,
                    $byId[$id]->contentHash,
                    $skill->contentHash,
                    $byId[$id]->canonicalLocation(),
                    $location,
                );
            }

            if (! isset($byId[$id])) {
                $byId[$id] = $skill;
                $canonical[] = $skill;
            }
        }

        usort($canonical, static fn (InstalledSkill $left, InstalledSkill $right): int => $left->manifest->id <=> $right->manifest->id);
        usort($conflicts, static fn (SkillConflict $left, SkillConflict $right): int => $left->skillId <=> $right->skillId);

        return new SkillCatalogResult($canonical, $conflicts);
    }
}
