<?php

declare(strict_types=1);

namespace Sifrious\Logres\Tests;

use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sifrious\Logres\CapabilitySnapshot;
use Sifrious\Logres\CurrentWorkload;
use Sifrious\Logres\PlatformIdentity;
use Sifrious\Logres\RunnerAvailability;
use Sifrious\Logres\RunnerDescriptor;
use Sifrious\Logres\RunnerIdentity;
use Sifrious\Logres\RunnerCompatibilityFailure;
use Sifrious\Logres\RunnerCompatibilityRequirements;

final class RunnerBoundaryTest extends TestCase
{
    #[Test]
    public function runner_descriptor_is_provider_neutral_and_deterministic(): void
    {
        $observedAt = new DateTimeImmutable('2026-08-29T12:00:00Z');
        $descriptor = new RunnerDescriptor(
            new RunnerIdentity('runner:workstation.alpha'),
            new PlatformIdentity('darwin', 'arm64'),
            new CapabilitySnapshot(['shell', 'agent', 'agent'], ['codex', 'amp'], ['2', '1'], $observedAt),
            RunnerAvailability::Available,
            new CurrentWorkload(1, 4),
            ['grant:workspace-alpha'],
            ['workspace:alpha'],
        );

        self::assertSame('runner:workstation.alpha', $descriptor->identity->value);
        self::assertSame('runner:workstation.alpha', $descriptor->identity->asExecutionNode()->value);
        self::assertSame(['agent', 'shell'], $descriptor->capabilities->capabilities);
        self::assertSame(['amp', 'codex'], $descriptor->capabilities->runtimeAdapters);
        self::assertSame(['1', '2'], $descriptor->capabilities->protocolVersions);
        self::assertSame($observedAt, $descriptor->capabilities->observedAt);
        self::assertSame(['grant:workspace-alpha'], $descriptor->authorizationGrantReferences);
        self::assertTrue($descriptor->compatibleWith(new RunnerCompatibilityRequirements('codex', '1', ['agent'], 'workspace:alpha', 'grant:workspace-alpha'))->compatible());
        self::assertSame(
            [RunnerCompatibilityFailure::RuntimeAdapterProfile, RunnerCompatibilityFailure::WorkspaceIdentity],
            $descriptor->compatibleWith(new RunnerCompatibilityRequirements('claude', '1', ['agent'], 'workspace:other', 'grant:workspace-alpha'))->failures,
        );
    }

    #[Test]
    public function runner_identity_requires_its_namespace(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new RunnerIdentity('workstation.alpha');
    }

    #[Test]
    public function capability_snapshot_rejects_empty_dimensions(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new CapabilitySnapshot([], ['codex'], ['1'], new DateTimeImmutable());
    }

    #[Test]
    public function workload_must_fit_within_capacity(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new CurrentWorkload(2, 1);
    }
}
