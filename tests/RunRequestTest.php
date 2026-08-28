<?php

declare(strict_types=1);

namespace Sifrious\Logres\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sifrious\Logres\RunRequest;
use Sifrious\Logres\Turn;

final class RunRequestTest extends TestCase
{
    #[Test]
    public function it_preserves_the_turn_harness_and_workspace(): void
    {
        $turn = new Turn(" exact\nprompt ");
        $request = new RunRequest($turn, 'fixture-harness', 'workspace-one');

        self::assertSame($turn, $request->turn);
        self::assertSame('fixture-harness', $request->harnessId);
        self::assertSame('workspace-one', $request->workspace);
    }
}
