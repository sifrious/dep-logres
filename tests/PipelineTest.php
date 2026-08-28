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
use Sifrious\Logres\RunRequest;
use Sifrious\Logres\RunResult;
use Sifrious\Logres\Turn;
use Sifrious\Logres\TurnContext;

final class PipelineTest extends TestCase
{
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
