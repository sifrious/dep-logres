<?php

declare(strict_types=1);

namespace Sifrious\Logres\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sifrious\Logres\RunStatus;

final class RunStatusTest extends TestCase
{
    #[Test]
    public function it_has_a_closed_status_vocabulary(): void
    {
        self::assertSame(
            ['pending', 'preparing', 'running', 'reconciling', 'needs_input', 'succeeded', 'failed', 'provider_error', 'timed_out', 'cancelled'],
            array_column(RunStatus::cases(), 'value'),
        );
    }

    #[Test]
    public function it_identifies_only_terminal_statuses(): void
    {
        self::assertFalse(RunStatus::Pending->isTerminal());
        self::assertFalse(RunStatus::NeedsInput->isTerminal());
        self::assertFalse(RunStatus::Reconciling->isTerminal());
        self::assertTrue(RunStatus::Succeeded->isTerminal());
        self::assertTrue(RunStatus::Failed->isTerminal());
        self::assertTrue(RunStatus::ProviderError->isTerminal());
        self::assertTrue(RunStatus::TimedOut->isTerminal());
        self::assertTrue(RunStatus::Cancelled->isTerminal());
    }
}
