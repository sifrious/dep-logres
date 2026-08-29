<?php

declare(strict_types=1);

namespace Sifrious\Logres\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sifrious\Logres\AfterTurnHandler;
use Sifrious\Logres\AfterTurnPipeline;
use Sifrious\Logres\BeforeTurnHandler;
use Sifrious\Logres\BeforeTurnPipeline;
use Sifrious\Logres\InvariantBeforeTurnHandler;
use Sifrious\Logres\InvariantPreflight;
use Sifrious\Logres\InvariantPreflightPhase;
use Sifrious\Logres\InvariantAfterTurnHandler;
use Sifrious\Logres\InvariantFinalization;
use Sifrious\Logres\InvariantFinalizationPhase;
use Sifrious\Logres\RequiredVerificationOutcome;
use Sifrious\Logres\RunRequest;
use Sifrious\Logres\RunResult;
use Sifrious\Logres\Turn;
use Sifrious\Logres\TurnContext;

final class PipelineTest extends TestCase
{
    #[Test]
    public function invariant_preflight_requires_every_phase_and_fails_closed_before_provider_dispatch(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invariant preflight phase Workspace is required.');

        new InvariantPreflight([
            new NamedInvariantHandler(InvariantPreflightPhase::Authorization, new Sequence),
            new NamedInvariantHandler(InvariantPreflightPhase::Provenance, new Sequence),
        ]);
    }

    #[Test]
    public function callers_cannot_reorder_the_invariant_core(): void
    {
        $sequence = new Sequence;
        $preflight = new InvariantPreflight([
            new NamedInvariantHandler(InvariantPreflightPhase::Provenance, $sequence),
            new NamedInvariantHandler(InvariantPreflightPhase::Authorization, $sequence),
            new NamedInvariantHandler(InvariantPreflightPhase::Workspace, $sequence),
        ]);

        $preflight->process(new RunRequest(new Turn('prompt'), 'fixture', 'workspace'), FixtureContext::make());

        self::assertSame(['Authorization', 'Workspace', 'Provenance'], $sequence->events);
    }
    #[Test]
    public function before_handlers_run_once_in_declared_order(): void
    {
        $sequence = new Sequence;
        $pipeline = new BeforeTurnPipeline([
            new InstructionHandler('first', $sequence),
            new InstructionHandler('second', $sequence),
        ]);
        $request = new RunRequest(new Turn('prompt'), 'fixture', 'workspace');

        $context = $pipeline->process($request, FixtureContext::make());

        self::assertSame(['first', 'second'], $sequence->events);
        self::assertSame(['first', 'second'], $context->instructions);
    }

    #[Test]
    #[DataProvider('terminalResults')]
    public function after_handlers_run_once_for_every_terminal_disposition(RunResult $result): void
    {
        $sequence = new Sequence;
        $pipeline = new AfterTurnPipeline([
            new ResultHandler('first', $sequence),
            new ResultHandler('second', $sequence),
        ]);
        $request = new RunRequest(new Turn('prompt'), 'fixture', 'workspace');

        $resolved = $pipeline->process($request, FixtureContext::make(), $result);

        self::assertSame($result, $resolved);
        self::assertSame(['first', 'second'], $sequence->events);
    }

    public static function terminalResults(): array
    {
        return [
            [RunResult::succeeded()],
            [RunResult::failed('failed', 1)],
            [RunResult::timedOut()],
            [RunResult::cancelled()],
            [RunResult::providerError('provider unavailable')],
        ];
    }

    #[Test]
    #[DataProvider('terminalResults')]
    public function invariant_finalization_runs_once_in_sealed_order_for_every_terminal_disposition(RunResult $result): void
    {
        $sequence = new Sequence;
        $pipeline = new InvariantFinalization(self::finalizers($sequence, RequiredVerificationOutcome::Passed));

        $resolved = $pipeline->process(new RunRequest(new Turn('prompt'), 'fixture', 'workspace'), FixtureContext::make(), $result);

        self::assertSame(array_map(static fn (InvariantFinalizationPhase $phase): string => $phase->name, InvariantFinalizationPhase::cases()), $sequence->events);
        self::assertSame(RequiredVerificationOutcome::Passed, $resolved->requiredVerification);
    }

    #[Test]
    public function provider_success_with_failed_verification_cannot_remain_canonical_success(): void
    {
        $pipeline = new InvariantFinalization(self::finalizers(new Sequence, RequiredVerificationOutcome::Failed));

        $resolved = $pipeline->process(
            new RunRequest(new Turn('prompt'), 'fixture', 'workspace'),
            FixtureContext::make(),
            RunResult::succeeded(agentClaim: 'everything passed'),
        );

        self::assertSame(\Sifrious\Logres\RunStatus::Failed, $resolved->status);
        self::assertSame(RequiredVerificationOutcome::Failed, $resolved->requiredVerification);
        self::assertSame('everything passed', $resolved->agentClaim);
    }

    #[Test]
    public function invariant_finalization_cannot_be_assembled_without_every_mandatory_phase(): void
    {
        $handlers = self::finalizers(new Sequence, RequiredVerificationOutcome::Passed);
        array_pop($handlers);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invariant finalization phase NormalizeProviderClaim is required.');

        new InvariantFinalization($handlers);
    }

    private static function finalizers(Sequence $sequence, RequiredVerificationOutcome $outcome): array
    {
        return array_map(
            static fn (InvariantFinalizationPhase $phase): NamedInvariantFinalizer => new NamedInvariantFinalizer($phase, $sequence, $outcome),
            array_reverse(InvariantFinalizationPhase::cases()),
        );
    }
}

final readonly class NamedInvariantFinalizer implements InvariantAfterTurnHandler
{
    public function __construct(
        private InvariantFinalizationPhase $invariantPhase,
        private Sequence $sequence,
        private RequiredVerificationOutcome $outcome,
    ) {}

    public function phase(): InvariantFinalizationPhase
    {
        return $this->invariantPhase;
    }

    public function handle(RunRequest $request, TurnContext $context, RunResult $result): RunResult
    {
        $this->sequence->events[] = $this->invariantPhase->name;

        return $this->invariantPhase === InvariantFinalizationPhase::Verify
            ? $result->withRequiredVerification($this->outcome)
            : $result;
    }
}

final readonly class NamedInvariantHandler implements InvariantBeforeTurnHandler
{
    public function __construct(
        private InvariantPreflightPhase $invariantPhase,
        private Sequence $sequence,
    ) {}

    public function phase(): InvariantPreflightPhase
    {
        return $this->invariantPhase;
    }

    public function handle(RunRequest $request, TurnContext $context): TurnContext
    {
        $this->sequence->events[] = $this->invariantPhase->name;

        return $context;
    }
}

final class Sequence
{
    public array $events = [];
}

final readonly class InstructionHandler implements BeforeTurnHandler
{
    public function __construct(
        private string $name,
        private Sequence $sequence,
    ) {}

    public function handle(RunRequest $request, TurnContext $context): TurnContext
    {
        $this->sequence->events[] = $this->name;

        return $context->withInstructions([...$context->instructions, $this->name]);
    }
}

final readonly class ResultHandler implements AfterTurnHandler
{
    public function __construct(
        private string $name,
        private Sequence $sequence,
    ) {}

    public function handle(RunRequest $request, TurnContext $context, RunResult $result): RunResult
    {
        $this->sequence->events[] = $this->name;

        return $result;
    }
}
