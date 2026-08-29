<?php

declare(strict_types=1);

namespace Sifrious\Logres\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sifrious\Logres\InvalidRunTransition;
use Sifrious\Logres\RunStatus;
use Sifrious\Logres\RunTransitionPolicy;

final class RunTransitionPolicyTest extends TestCase
{
    #[Test]
    #[DataProvider('allowedTransitions')]
    public function it_allows_declared_transitions(RunStatus $from, RunStatus $to): void
    {
        self::assertTrue(RunTransitionPolicy::allows($from, $to));
        RunTransitionPolicy::assertAllowed($from, $to);
        self::addToAssertionCount(1);
    }

    public static function allowedTransitions(): array
    {
        return [
            [RunStatus::Pending, RunStatus::Preparing],
            [RunStatus::Pending, RunStatus::TimedOut],
            [RunStatus::Pending, RunStatus::Cancelled],
            [RunStatus::Preparing, RunStatus::Running],
            [RunStatus::Preparing, RunStatus::Reconciling],
            [RunStatus::Preparing, RunStatus::NeedsInput],
            [RunStatus::Preparing, RunStatus::Failed],
            [RunStatus::Preparing, RunStatus::TimedOut],
            [RunStatus::Preparing, RunStatus::Cancelled],
            [RunStatus::Running, RunStatus::NeedsInput],
            [RunStatus::Running, RunStatus::Reconciling],
            [RunStatus::Running, RunStatus::Succeeded],
            [RunStatus::Running, RunStatus::Failed],
            [RunStatus::Running, RunStatus::TimedOut],
            [RunStatus::Running, RunStatus::Cancelled],
            [RunStatus::NeedsInput, RunStatus::Preparing],
            [RunStatus::NeedsInput, RunStatus::Failed],
            [RunStatus::NeedsInput, RunStatus::TimedOut],
            [RunStatus::NeedsInput, RunStatus::Cancelled],
            [RunStatus::Reconciling, RunStatus::Preparing],
            [RunStatus::Reconciling, RunStatus::Running],
            [RunStatus::Reconciling, RunStatus::Failed],
            [RunStatus::Reconciling, RunStatus::TimedOut],
            [RunStatus::Reconciling, RunStatus::Cancelled],
        ];
    }

    #[Test]
    #[DataProvider('terminalStatuses')]
    public function it_rejects_restarting_a_terminal_run(RunStatus $status): void
    {
        $this->expectException(InvalidRunTransition::class);

        RunTransitionPolicy::assertAllowed($status, RunStatus::Preparing);
    }

    public static function terminalStatuses(): array
    {
        return [
            [RunStatus::Succeeded],
            [RunStatus::Failed],
            [RunStatus::TimedOut],
            [RunStatus::Cancelled],
        ];
    }
}
