<?php

declare(strict_types=1);

namespace Sifrious\Logres\Tests\Fixtures;

use Sifrious\Logres\InvariantAfterTurnHandler;
use Sifrious\Logres\InvariantBeforeTurnHandler;
use Sifrious\Logres\InvariantFinalization;
use Sifrious\Logres\InvariantFinalizationPhase;
use Sifrious\Logres\InvariantPreflight;
use Sifrious\Logres\InvariantPreflightPhase;
use Sifrious\Logres\RequiredVerificationOutcome;
use Sifrious\Logres\RunRequest;
use Sifrious\Logres\RunResult;
use Sifrious\Logres\TurnContext;

final class InvariantPipelines
{
    public static function preflight(): InvariantPreflight
    {
        return new InvariantPreflight(array_map(
            static fn (InvariantPreflightPhase $phase): FixturePreflight => new FixturePreflight($phase),
            InvariantPreflightPhase::cases(),
        ));
    }

    public static function finalization(): InvariantFinalization
    {
        return new InvariantFinalization(array_map(
            static fn (InvariantFinalizationPhase $phase): FixtureFinalization => new FixtureFinalization($phase),
            InvariantFinalizationPhase::cases(),
        ));
    }
}

final readonly class FixturePreflight implements InvariantBeforeTurnHandler
{
    public function __construct(private InvariantPreflightPhase $invariantPhase) {}

    public function phase(): InvariantPreflightPhase
    {
        return $this->invariantPhase;
    }

    public function handle(RunRequest $request, TurnContext $context): TurnContext
    {
        return $context;
    }
}

final readonly class FixtureFinalization implements InvariantAfterTurnHandler
{
    public function __construct(private InvariantFinalizationPhase $invariantPhase) {}

    public function phase(): InvariantFinalizationPhase
    {
        return $this->invariantPhase;
    }

    public function handle(RunRequest $request, TurnContext $context, RunResult $result): RunResult
    {
        return $this->invariantPhase === InvariantFinalizationPhase::Verify
            ? $result->withRequiredVerification(RequiredVerificationOutcome::Passed)
            : $result;
    }
}
