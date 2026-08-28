<?php

declare(strict_types=1);

namespace Sifrious\Logres\Tests;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sifrious\Logres\AlwaysOnSkillResolver;
use Sifrious\Logres\SkillDependencyGraph;
use Sifrious\Logres\SkillManifest;

final class SkillManifestTest extends TestCase
{
    #[Test]
    public function it_validates_the_complete_manifest_contract(): void
    {
        $manifest = SkillManifest::fromArray(self::manifest('verify', ['prepare'], true));

        self::assertSame('verify', $manifest->id);
        self::assertSame(['prepare'], $manifest->dependencies);
        self::assertSame(['scripts/check.php'], $manifest->resources);
        self::assertTrue($manifest->alwaysOn);
    }

    #[Test]
    public function it_rejects_a_missing_contract_field(): void
    {
        $manifest = self::manifest('verify');
        unset($manifest['completion']);

        $this->expectException(InvalidArgumentException::class);

        SkillManifest::fromArray($manifest);
    }

    #[Test]
    public function it_builds_a_stable_graph_and_reports_unresolved_dependencies(): void
    {
        $verify = SkillManifest::fromArray(self::manifest('verify', ['prepare', 'missing']));
        $prepare = SkillManifest::fromArray(self::manifest('prepare'));
        $graph = new SkillDependencyGraph([$verify, $prepare]);

        self::assertSame(['missing', 'prepare'], $graph->dependenciesOf('verify'));
        self::assertSame(['verify' => ['missing']], $graph->unresolved());
    }

    #[Test]
    public function it_resolves_only_always_on_skills_in_stable_order(): void
    {
        $resolved = AlwaysOnSkillResolver::resolve([
            SkillManifest::fromArray(self::manifest('zeta', alwaysOn: true)),
            SkillManifest::fromArray(self::manifest('contextual')),
            SkillManifest::fromArray(self::manifest('alpha', alwaysOn: true)),
        ]);

        self::assertSame(['alpha', 'zeta'], array_map(static fn (SkillManifest $skill): string => $skill->id, $resolved));
    }

    private static function manifest(string $id, array $dependencies = [], bool $alwaysOn = false): array
    {
        return [
            'id' => $id,
            'inputs' => ['prompt' => ['type' => 'string']],
            'outputs' => ['result' => ['type' => 'string']],
            'dependencies' => $dependencies,
            'lifecycle' => 'turn',
            'triggers' => ['explicit'],
            'exclusions' => [],
            'gates' => [],
            'completion' => ['result exists'],
            'resources' => ['scripts/check.php'],
            'always_on' => $alwaysOn,
        ];
    }
}
