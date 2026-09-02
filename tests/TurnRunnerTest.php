<?php

declare(strict_types=1);

namespace Sifrious\Logres\Tests;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sifrious\Logres\AbstractHarness;
use Sifrious\Logres\AfterTurnHandler;
use Sifrious\Logres\AfterTurnPipeline;
use Sifrious\Logres\ArtifactReference;
use Sifrious\Logres\BeforeTurnHandler;
use Sifrious\Logres\BeforeTurnPipeline;
use Sifrious\Logres\EnvironmentSnapshot;
use Sifrious\Logres\ExecutionObserver;
use Sifrious\Logres\FinalizationStatus;
use Sifrious\Logres\HarnessCapability;
use Sifrious\Logres\HarnessHandle;
use Sifrious\Logres\HarnessProbe;
use Sifrious\Logres\HarnessStatus;
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
use Sifrious\Logres\TurnRunner;
use UnexpectedValueException;

final class TurnRunnerTest extends TestCase
{
    #[Test]
    public function a_failing_invariant_keeps_the_provider_call_count_at_zero(): void
    {
        $sequence = new Sequence;
        $runner = new TurnRunner(
            new InvariantPreflight([
                new SequencedInvariantHandler(InvariantPreflightPhase::Authorization, $sequence),
                new ThrowingInvariantHandler(InvariantPreflightPhase::Workspace, $sequence),
                new SequencedInvariantHandler(InvariantPreflightPhase::Provenance, $sequence),
            ]),
            new BeforeTurnPipeline,
            new InvariantFinalization(FinalizationFixtures::passing($sequence)),
            new AfterTurnPipeline,
        );

        try {
            $runner->run(
                new RunRequest(new Turn('exact prompt'), 'fixture', 'workspace'),
                FixtureContext::make(),
                new SequencedHarness($sequence),
                new RecordingExecutionObserver($sequence),
            );
            self::fail('The failing workspace invariant should stop execution.');
        } catch (UnexpectedValueException $exception) {
            self::assertSame('workspace rejected', $exception->getMessage());
        }

        self::assertSame(['invariant:Authorization', 'invariant:Workspace'], $sequence->events);
        self::assertNotContains('start', $sequence->events);
    }

    #[Test]
    public function it_runs_the_kernel_in_exact_order(): void
    {
        $sequence = new Sequence;
        $request = new RunRequest(new Turn('exact prompt'), 'fixture', 'workspace');
        $context = FixtureContext::make();
        $observer = new RecordingExecutionObserver($sequence);
        $harness = new SequencedHarness($sequence);
        $runner = new TurnRunner(
            new InvariantPreflight([
                new SequencedInvariantHandler(InvariantPreflightPhase::Provenance, $sequence),
                new SequencedInvariantHandler(InvariantPreflightPhase::Authorization, $sequence),
                new SequencedInvariantHandler(InvariantPreflightPhase::Workspace, $sequence),
            ]),
            new BeforeTurnPipeline([new SequencedBeforeHandler($sequence)]),
            new InvariantFinalization(FinalizationFixtures::passing($sequence)),
            new AfterTurnPipeline([new SequencedAfterHandler($sequence)]),
        );

        $result = $runner->run($request, $context, $harness, $observer);

        self::assertEquals(RunResult::succeeded('complete')->withRequiredVerification(RequiredVerificationOutcome::Passed), $result);
        self::assertSame([
            'invariant:Authorization',
            'invariant:Workspace',
            'invariant:Provenance',
            'before',
            'context',
            'start',
            'process',
            'status:running',
            'stdout',
            'stderr',
            'artifact',
            'status:succeeded',
            'finalize:NormalizeProviderClaim',
            'finalize:ObserveEndingState',
            'finalize:Verify',
            'finalize:AssembleCanonicalResult',
            'finalize:PersistOperationalResult',
            'finalize:ScheduleHistorianExport',
            'after',
        ], $sequence->events);
    }

    #[Test]
    public function optional_after_handlers_cannot_replace_the_canonical_disposition(): void
    {
        $sequence = new Sequence;
        $runner = new TurnRunner(
            new InvariantPreflight([
                new SequencedInvariantHandler(InvariantPreflightPhase::Authorization, $sequence),
                new SequencedInvariantHandler(InvariantPreflightPhase::Workspace, $sequence),
                new SequencedInvariantHandler(InvariantPreflightPhase::Provenance, $sequence),
            ]),
            new BeforeTurnPipeline,
            new InvariantFinalization(FinalizationFixtures::passing($sequence)),
            new AfterTurnPipeline([new ReplacingAfterHandler]),
        );

        $result = $runner->run(
            new RunRequest(new Turn('exact prompt'), 'fixture', 'workspace'),
            FixtureContext::make(),
            new SequencedHarness($sequence),
            new RecordingExecutionObserver($sequence),
        );

        self::assertSame(FinalizationStatus::Incomplete, $result->finalizationStatus);
        self::assertFalse($result->isVerifiedSuccess());
    }
}

final readonly class ReplacingAfterHandler implements AfterTurnHandler
{
    public function handle(RunRequest $request, TurnContext $context, RunResult $result): RunResult
    {
        return RunResult::failed('attempted replacement', 1);
    }
}

final class FinalizationFixtures
{
    public static function passing(Sequence $sequence): array
    {
        return array_map(
            static fn (InvariantFinalizationPhase $phase): SequencedFinalizer => new SequencedFinalizer($phase, $sequence),
            array_reverse(InvariantFinalizationPhase::cases()),
        );
    }
}

final readonly class SequencedFinalizer implements InvariantAfterTurnHandler
{
    public function __construct(
        private InvariantFinalizationPhase $invariantPhase,
        private Sequence $sequence,
    ) {}

    public function phase(): InvariantFinalizationPhase
    {
        return $this->invariantPhase;
    }

    public function handle(RunRequest $request, TurnContext $context, RunResult $result): RunResult
    {
        $this->sequence->events[] = "finalize:{$this->invariantPhase->name}";

        return $this->invariantPhase === InvariantFinalizationPhase::Verify
            ? $result->withRequiredVerification(RequiredVerificationOutcome::Passed)
            : $result;
    }
}

final readonly class ThrowingInvariantHandler implements InvariantBeforeTurnHandler
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
        $this->sequence->events[] = "invariant:{$this->invariantPhase->name}";

        throw new UnexpectedValueException('workspace rejected');
    }
}

final readonly class SequencedInvariantHandler implements InvariantBeforeTurnHandler
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
        $this->sequence->events[] = "invariant:{$this->invariantPhase->name}";

        return $context;
    }
}

final readonly class SequencedBeforeHandler implements BeforeTurnHandler
{
    public function __construct(private Sequence $sequence) {}

    public function handle(RunRequest $request, TurnContext $context): TurnContext
    {
        $this->sequence->events[] = 'before';

        return $context->withInstructions(['resolved']);
    }
}

final readonly class SequencedAfterHandler implements AfterTurnHandler
{
    public function __construct(private Sequence $sequence) {}

    public function handle(RunRequest $request, TurnContext $context, RunResult $result): RunResult
    {
        $this->sequence->events[] = 'after';
        if ($context->instructions !== ['resolved']) {
            throw new UnexpectedValueException('The resolved context did not reach the after-turn handler.');
        }

        return $result;
    }
}

final class SequencedHarness extends AbstractHarness
{
    private int $statusChecks = 0;

    public function __construct(private readonly Sequence $sequence)
    {
        parent::__construct('fixture', new HarnessCapability('fixture', true, true, true, false));
    }

    public function probe(): HarnessProbe
    {
        return HarnessProbe::available(new EnvironmentSnapshot('fixture-os', '0.1.0', 'fixture-1', '/bin/fixture'));
    }

    public function status(HarnessHandle $handle, ExecutionObserver $observer): HarnessStatus
    {
        $this->statusChecks++;

        if ($this->statusChecks === 1) {
            $this->sequence->events[] = 'status:running';

            return HarnessStatus::active();
        }

        $observer->stdout('complete');
        $observer->stderr('diagnostic');
        $observer->artifact(new ArtifactReference('artifact-1', 'fixture', 'artifact.txt', 'text/plain', 8, 'sha256:fixture'));
        $this->sequence->events[] = 'status:succeeded';

        return HarnessStatus::terminal(RunResult::succeeded('complete'));
    }

    public function cancel(HarnessHandle $handle): void {}

    protected function startHarness(RunRequest $request, TurnContext $context, ExecutionObserver $observer): HarnessHandle
    {
        $this->sequence->events[] = 'start';

        return new HarnessHandle('attempt-1', $this->id(), new DateTimeImmutable('2026-08-27T12:00:00+00:00'));
    }
}

final readonly class RecordingExecutionObserver implements ExecutionObserver
{
    public function __construct(private Sequence $sequence) {}

    public function contextResolved(TurnContext $context): void
    {
        $this->sequence->events[] = 'context';
    }

    public function processStarted(HarnessHandle $handle): void
    {
        $this->sequence->events[] = 'process';
    }

    public function stdout(string $chunk): void
    {
        $this->sequence->events[] = 'stdout';
    }

    public function stderr(string $chunk): void
    {
        $this->sequence->events[] = 'stderr';
    }

    public function artifact(ArtifactReference $artifact): void
    {
        $this->sequence->events[] = 'artifact';
    }
}
