<?php

declare(strict_types=1);

namespace Sifrious\Logres\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sifrious\Logres\EnvironmentSnapshot;
use Sifrious\Logres\TurnContext;

final class TurnContextTest extends TestCase
{
    #[Test]
    public function it_composes_context_without_mutating_the_source(): void
    {
        $environment = new EnvironmentSnapshot('Darwin', '0.1.0', 'fixture 1.0', '/bin/fixture', ['streaming']);
        $source = new TurnContext(['operator' => 'local'], $environment);
        $withInstructions = $source->withInstructions(['Preserve exact bytes.']);
        $withSkills = $withInstructions->withSkills([['id' => 'verify']]);
        $complete = $withSkills->withTools([['id' => 'filesystem']]);

        self::assertSame([], $source->instructions);
        self::assertSame([], $withInstructions->skills);
        self::assertSame([], $withSkills->tools);
        self::assertSame(['Preserve exact bytes.'], $complete->instructions);
        self::assertSame([['id' => 'verify']], $complete->skills);
        self::assertSame([['id' => 'filesystem']], $complete->tools);
        self::assertSame($environment, $complete->environment);
    }
}
