<?php

declare(strict_types=1);

namespace Sifrious\Logres\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sifrious\Logres\InstalledSkill;
use Sifrious\Logres\SkillCatalog;
use Sifrious\Logres\SkillManifest;

final class SkillCatalogTest extends TestCase
{
    #[Test]
    public function duplicate_locations_collapse_and_content_conflicts_remain_explicit(): void
    {
        $manifest = new SkillManifest('verify', [], [], [], 'turn', [], [], [], []);
        $first = new InstalledSkill($manifest, '/skills/verify', 'sha256:first');
        $duplicate = new InstalledSkill($manifest, '/skills/verify', 'sha256:first');
        $conflict = new InstalledSkill($manifest, '/other/verify', 'sha256:second');

        $result = SkillCatalog::canonicalize([$duplicate, $conflict, $first]);

        self::assertCount(1, $result->skills);
        self::assertCount(1, $result->conflicts);
        self::assertSame('verify', $result->conflicts[0]->skillId);
        self::assertSame('sha256:first', $result->conflicts[0]->firstHash);
        self::assertSame('sha256:second', $result->conflicts[0]->secondHash);
    }
}
