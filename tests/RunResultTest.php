<?php

declare(strict_types=1);

namespace Sifrious\Logres\Tests;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sifrious\Logres\RunResult;
use Sifrious\Logres\RunStatus;

final class RunResultTest extends TestCase
{
    #[Test]
    public function it_carries_each_terminal_disposition(): void
    {
        $succeeded = RunResult::succeeded('out', 'diagnostic');
        $failed = RunResult::failed('failure', 23, 'partial');
        $timedOut = RunResult::timedOut('partial out', 'partial error', 'deadline');
        $cancelled = RunResult::cancelled('out before cancel', '', 'operator');

        self::assertSame([RunStatus::Succeeded, 0, 'out', 'diagnostic'], [$succeeded->status, $succeeded->exitCode, $succeeded->stdout, $succeeded->stderr]);
        self::assertSame([RunStatus::Failed, 23, null, 'partial', 'failure'], [$failed->status, $failed->exitCode, $failed->signal, $failed->stdout, $failed->stderr]);
        self::assertSame([RunStatus::TimedOut, 'deadline'], [$timedOut->status, $timedOut->reason]);
        self::assertSame([RunStatus::Cancelled, 'operator'], [$cancelled->status, $cancelled->reason]);
    }

    #[Test]
    public function it_rejects_a_nonterminal_result(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new RunResult(RunStatus::Running);
    }

    #[Test]
    public function it_rejects_a_nonzero_success(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new RunResult(RunStatus::Succeeded, exitCode: 1);
    }

    #[Test]
    public function it_rejects_exit_code_zero_for_a_failure(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new RunResult(RunStatus::Failed, exitCode: 0);
    }
}
