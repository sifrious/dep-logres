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
use Sifrious\Logres\HarnessCapability;
use Sifrious\Logres\HarnessHandle;
use Sifrious\Logres\HarnessProbe;
use Sifrious\Logres\HarnessStatus;
use Sifrious\Logres\RunRequest;
use Sifrious\Logres\RunResult;
use Sifrious\Logres\Turn;
use Sifrious\Logres\TurnContext;
use Sifrious\Logres\TurnRunner;
use UnexpectedValueException;

final class TurnRunnerTest extends TestCase
{
    #[Test]
    public function it_runs_the_kernel_in_exact_order(): void
    {
        $sequence = new Sequence;
        $request = new RunRequest(new Turn('exact prompt'), 'fixture', 'workspace');
        $context = FixtureContext::make();
        $observer = new RecordingExecutionObserver($sequence);
        $harness = new SequencedHarness($sequence);
        $runner = new TurnRunner(
            new BeforeTurnPipeline([new SequencedBeforeHandler($sequence)]),
            new AfterTurnPipeline([new SequencedAfterHandler($sequence)]),
        );

        $result = $runner->run($request, $context, $harness, $observer);

        self::assertEquals(RunResult::succeeded('complete'), $result);
        self::assertSame([
            'before',
            'context',
            'start',
            'process',
            'status:running',
            'stdout',
            'stderr',
            'artifact',
            'status:succeeded',
            'after',
        ], $sequence->events);
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
