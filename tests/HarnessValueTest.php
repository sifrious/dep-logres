<?php

declare(strict_types=1);

namespace Sifrious\Logres\Tests;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sifrious\Logres\EnvironmentSnapshot;
use Sifrious\Logres\HarnessCapability;
use Sifrious\Logres\HarnessHandle;
use Sifrious\Logres\HarnessProbe;

final class HarnessValueTest extends TestCase
{
    #[Test]
    public function it_carries_environment_and_capability_facts(): void
    {
        $environment = new EnvironmentSnapshot('Darwin 25', '0.1.0', 'fixture 2.4', '/usr/local/bin/fixture', ['streaming', 'cancel']);
        $capability = new HarnessCapability('fixture', true, true, true, false);

        self::assertSame('/usr/local/bin/fixture', $environment->executable);
        self::assertSame(['streaming', 'cancel'], $environment->capabilities);
        self::assertSame('fixture', $capability->transport);
        self::assertTrue($capability->streamsOutput);
        self::assertTrue($capability->supportsCancellation);
        self::assertTrue($capability->producesArtifacts);
        self::assertFalse($capability->supportsInteraction);
    }

    #[Test]
    public function it_distinguishes_available_and_unavailable_probes(): void
    {
        $environment = new EnvironmentSnapshot('Darwin', '0.1.0', 'fixture 1.0', '/bin/fixture');
        $available = HarnessProbe::available($environment);
        $unavailable = HarnessProbe::unavailable('Executable not found.');

        self::assertTrue($available->available);
        self::assertSame($environment, $available->environment);
        self::assertFalse($unavailable->available);
        self::assertSame('Executable not found.', $unavailable->reason);
    }

    #[Test]
    public function it_carries_provider_neutral_attempt_identity(): void
    {
        $startedAt = new DateTimeImmutable('2026-08-27T12:00:00+00:00');
        $handle = new HarnessHandle('attempt-01', 'fixture-harness', $startedAt);

        self::assertSame('attempt-01', $handle->attemptId);
        self::assertSame('fixture-harness', $handle->harnessId);
        self::assertSame($startedAt, $handle->startedAt);
    }
}
