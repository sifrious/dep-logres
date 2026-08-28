<?php

declare(strict_types=1);

namespace Sifrious\Logres;

final readonly class SkillConflict
{
    public function __construct(
        public string $skillId,
        public string $firstHash,
        public string $secondHash,
        public string $firstLocation,
        public string $secondLocation,
    ) {}
}
