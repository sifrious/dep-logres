<?php

declare(strict_types=1);

namespace Sifrious\Logres;

final readonly class SkillCatalogResult
{
    public function __construct(
        public array $skills,
        public array $conflicts,
    ) {}
}
