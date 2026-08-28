<?php

declare(strict_types=1);

namespace Sifrious\Logres\Tests;

use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sifrious\Logres\AbstractHarness;
use Sifrious\Logres\ArtifactReference;
use Sifrious\Logres\EnvironmentSnapshot;
use Sifrious\Logres\ExecutionObserver;
use Sifrious\Logres\HarnessCapability;
use Sifrious\Logres\HarnessHandle;
use Sifrious\Logres\HarnessProbe;
use Sifrious\Logres\HarnessRegistry;
use Sifrious\Logres\HarnessStatus;
use Sifrious\Logres\RunRequest;
use Sifrious\Logres\RunResult;
use Sifrious\Logres\Turn;
use Sifrious\Logres\TurnContext;

final class HarnessContractTest extends TestCase
{
    #[Test]
    public function two_harnesses_share_identity_capability_and_request_validation(): void
    {
        $capability = new HarnessCapability('fixture', true, true, false, false);
        $first = new FirstFixtureHarness('first', $capability);
        $second = new SecondFixtureHarness('second', $capability);
        $registry = new HarnessRegistry([$second, $first]);

        self::assertSame(['first', 'second'], array_map(static fn ($harness): string => $harness->id(), $registry->all()));
        self::assertSame($capability, $registry->get('first')->capabilities());

        $this->expectException(InvalidArgumentException::class);
        $first->start(
            new RunRequest(new Turn('prompt'), 'second', 'workspace'),
            FixtureContext::make(),
            new NullObserver,
        );
    }

    #[Test]
    public function duplicate_harness_ids_fail_deterministically(): void
    {
        $capability = new HarnessCapability('fixture', true, true, false, false);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Harness fixture is already registered.');

        new HarnessRegistry([
            new FirstFixtureHarness('fixture', $capability),
            new SecondFixtureHarness('fixture', $capability),
        ]);
    }

    #[Test]
    public function a_started_handle_retains_the_registered_harness_id(): void
    {
        $harness = new FirstFixtureHarness('fixture', new HarnessCapability('fixture', true, true, false, false));
        $handle = $harness->start(
            new RunRequest(new Turn('prompt'), 'fixture', 'workspace'),
            FixtureContext::make(),
            new NullObserver,
        );

        self::assertSame('fixture', $handle->harnessId);
        self::assertSame('fixture-attempt', $handle->attemptId);
    }
}

final class FirstFixtureHarness extends AbstractHarness
{
    public function probe(): HarnessProbe
    {
        return HarnessProbe::available(FixtureContext::environment());
    }

    public function status(HarnessHandle $handle, ExecutionObserver $observer): HarnessStatus
    {
        return HarnessStatus::terminal(RunResult::succeeded());
    }

    public function cancel(HarnessHandle $handle): void {}

    protected function startHarness(RunRequest $request, TurnContext $context, ExecutionObserver $observer): HarnessHandle
    {
        return new HarnessHandle($this->id().'-attempt', $this->id(), new DateTimeImmutable('2026-08-27T12:00:00+00:00'));
    }
}

final class SecondFixtureHarness extends AbstractHarness
{
    public function probe(): HarnessProbe
    {
        return HarnessProbe::available(FixtureContext::environment());
    }

    public function status(HarnessHandle $handle, ExecutionObserver $observer): HarnessStatus
    {
        return HarnessStatus::terminal(RunResult::succeeded());
    }

    public function cancel(HarnessHandle $handle): void {}

    protected function startHarness(RunRequest $request, TurnContext $context, ExecutionObserver $observer): HarnessHandle
    {
        return new HarnessHandle($this->id().'-attempt', $this->id(), new DateTimeImmutable('2026-08-27T12:00:00+00:00'));
    }
}

final class NullObserver implements ExecutionObserver
{
    public function contextResolved(TurnContext $context): void {}

    public function processStarted(HarnessHandle $handle): void {}

    public function stdout(string $chunk): void {}

    public function stderr(string $chunk): void {}

    public function artifact(ArtifactReference $artifact): void {}
}

final class FixtureContext
{
    public static function make(): TurnContext
    {
        return new TurnContext(['operator' => 'fixture'], self::environment());
    }

    public static function environment(): EnvironmentSnapshot
    {
        return new EnvironmentSnapshot('fixture-os', '0.1.0', 'fixture-1', '/bin/fixture');
    }
}
