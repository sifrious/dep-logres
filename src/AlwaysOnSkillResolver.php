<?php

declare(strict_types=1);

namespace Sifrious\Logres;

use InvalidArgumentException;

final class AlwaysOnSkillResolver
{
    public static function resolve(iterable $manifests): array
    {
        $resolved = [];

        foreach ($manifests as $manifest) {
            if (! $manifest instanceof SkillManifest) {
                throw new InvalidArgumentException('Always-on resolution accepts only SkillManifest values.');
            }

            if ($manifest->alwaysOn) {
                $resolved[] = $manifest;
            }
        }

        usort($resolved, static fn (SkillManifest $left, SkillManifest $right): int => $left->id <=> $right->id);

        return $resolved;
    }
}
