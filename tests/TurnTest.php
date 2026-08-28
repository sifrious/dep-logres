<?php

declare(strict_types=1);

namespace Sifrious\Logres\Tests;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sifrious\Logres\Turn;

final class TurnTest extends TestCase
{
    #[Test]
    public function it_preserves_exact_prompt_bytes_and_references(): void
    {
        $prompt = "  first line\nλ 'quoted' \$PATH\n";
        $references = [
            ['kind' => 'file', 'value' => 'docs/input.md'],
            ['kind' => 'url', 'value' => 'https://example.test/context'],
        ];

        $turn = new Turn($prompt, $references);

        self::assertSame($prompt, $turn->prompt);
        self::assertSame($references, $turn->references);
    }

    #[Test]
    public function it_rejects_a_blank_prompt(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Turn(" \n\t ");
    }
}
