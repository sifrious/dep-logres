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
        ];
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
