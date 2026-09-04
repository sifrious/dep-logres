<?php

declare(strict_types=1);

namespace Sifrious\Logres\Tests;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sifrious\Elwin\Handoff\HandoffPayload;
use Sifrious\Elwin\Handoff\ResumableHandoff;
use Sifrious\Elwin\Handoff\ResumeContext;
use Sifrious\Logres\AbstractHarness;
use Sifrious\Logres\AfterTurnHandler;
use Sifrious\Logres\AfterTurnPipeline;
use Sifrious\Logres\ArtifactAccessClassification;
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
use Sifrious\Logres\HumanGate;
use Sifrious\Logres\HumanInputAuthorization;
use Sifrious\Logres\InvariantBeforeTurnHandler;
use Sifrious\Logres\InvariantPreflight;
use Sifrious\Logres\InvariantPreflightPhase;
use Sifrious\Logres\InvariantAfterTurnHandler;
use Sifrious\Logres\InvariantFinalization;
use Sifrious\Logres\InvariantFinalizationPhase;
use Sifrious\Logres\RequiredVerificationOutcome;
use Sifrious\Logres\RunRequest;
use Sifrious\Logres\RunResult;
use Sifrious\Logres\RunId;
use Sifrious\Logres\RunStatus;
use Sifrious\Logres\NeedsInput;
use Sifrious\Logres\Turn;
use Sifrious\Logres\TurnContext;
use Sifrious\Logres\TurnRunner;
use Sifrious\Logres\Tests\Fixtures\InMemoryTurnCheckpointStore;
use Sifrious\ReferenceContract\CrossPackageReference;
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

    #[Test]
    public function resume_uses_the_elwin_checkpoint_without_replaying_completed_handlers(): void
    {
        $sequence = new Sequence;
        $checkpoints = new InMemoryTurnCheckpointStore();
        $request = new RunRequest(new Turn('exact prompt'), 'pausing-fixture', 'workspace', 'run:turn:1');
        $at = new DateTimeImmutable('2026-09-04T12:00:00Z');
        $handoff = new ResumableHandoff(
            'handoff:turn:1',
            new CrossPackageReference('sifrious/elwin', 'conversation', 'conversation:1'),
            new CrossPackageReference('sifrious/logres', 'run', $request->identity()),
            new CrossPackageReference('sifrious/elwin', 'question', 'question:1'),
            new ResumeContext(
                'resume-secret',
                new CrossPackageReference('sifrious/logres', 'turn-checkpoint', 'checkpoint:turn:1'),
            ),
            new HandoffPayload('sifrious.elwin.intervention-context/v1', ['summary' => 'Continue?']),
            $at,
            $at->modify('+1 hour'),
        );
        $runner = new TurnRunner(
            new InvariantPreflight([
                new SequencedInvariantHandler(InvariantPreflightPhase::Authorization, $sequence),
                new SequencedInvariantHandler(InvariantPreflightPhase::Workspace, $sequence),
                new SequencedInvariantHandler(InvariantPreflightPhase::Provenance, $sequence),
            ]),
            new BeforeTurnPipeline([new SequencedBeforeHandler($sequence)]),
            new InvariantFinalization(FinalizationFixtures::passing($sequence)),
            new AfterTurnPipeline([new SequencedAfterHandler($sequence)]),
            checkpoints: $checkpoints,
        );
        $harness = new PausingHarness($sequence, $handoff);
        $observer = new RecordingExecutionObserver($sequence);

        try {
            $runner->run($request, FixtureContext::make(), $harness, $observer);
            self::fail('The first invocation should pause.');
        } catch (NeedsInput $pause) {
            self::assertSame('handoff:turn:1', $pause->handoff->id);
        }

        $answered = $handoff->answer(
            new CrossPackageReference('sifrious/elwin', 'response', 'response:1'),
            $at->modify('+1 minute'),
        );
        $result = $runner->resume(
            $request,
            $answered,
            HumanInputAuthorization::allow(),
            $at->modify('+2 minutes'),
            $harness,
            $observer,
        );

        self::assertSame(RunStatus::Succeeded, $result->status);
        self::assertSame(RequiredVerificationOutcome::Passed, $result->requiredVerification);
        self::assertSame(1, count(array_filter($sequence->events, static fn (string $event): bool => $event === 'before')));
        self::assertSame(1, count(array_filter($sequence->events, static fn (string $event): bool => $event === 'context')));
        self::assertSame(2, count(array_filter($sequence->events, static fn (string $event): bool => $event === 'start')));
        self::assertSame(1, count(array_filter($sequence->events, static fn (string $event): bool => $event === 'invariant:Authorization')));
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
        $observer->artifact(new ArtifactReference(
            id: 'artifact-1',
            runId: new RunId('run:fixture'),
            type: 'bounded_log',
            locator: 'artifact.txt',
            mediaType: 'text/plain',
            size: 8,
            integrity: 'sha256:fixture',
            accessClassification: ArtifactAccessClassification::Internal,
        ));
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

final class PausingHarness extends AbstractHarness
{
    private bool $paused = false;

    public function __construct(
        private readonly Sequence $sequence,
        private readonly ResumableHandoff $handoff,
    ) {
        parent::__construct('pausing-fixture', new HarnessCapability('fixture', true, true, true, false));
    }

    public function probe(): HarnessProbe
    {
        return HarnessProbe::available(new EnvironmentSnapshot('fixture-os', '0.1.0', 'fixture-1', '/bin/fixture'));
    }

    public function status(HarnessHandle $handle, ExecutionObserver $observer): HarnessStatus
    {
        $this->sequence->events[] = 'status:succeeded';

        return HarnessStatus::terminal(RunResult::succeeded('complete'));
    }

    public function cancel(HarnessHandle $handle): void {}

    protected function startHarness(RunRequest $request, TurnContext $context, ExecutionObserver $observer): HarnessHandle
    {
        $this->sequence->events[] = 'start';
        if (! $this->paused) {
            $this->paused = true;
            HumanGate::pause($this->handoff);
        }

        return new HarnessHandle('attempt-1', $this->id(), new DateTimeImmutable('2026-09-04T12:02:00Z'));
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
