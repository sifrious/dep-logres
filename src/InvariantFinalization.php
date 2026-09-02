<?php

declare(strict_types=1);

namespace Sifrious\Logres;

use InvalidArgumentException;
use LogicException;

/** Sealed terminal-result finalization that runs in package-owned phase order. */
final readonly class InvariantFinalization
{
    /** @var list<InvariantAfterTurnHandler> */
    private array $handlers;

    public function __construct(iterable $handlers)
    {
        $byPhase = [];

        foreach ($handlers as $handler) {
            if (! $handler instanceof InvariantAfterTurnHandler) {
                throw new InvalidArgumentException('Every invariant finalization handler must implement InvariantAfterTurnHandler.');
            }

            $phase = $handler->phase();
            if (isset($byPhase[$phase->value])) {
                throw new InvalidArgumentException("Invariant finalization phase {$phase->name} may only be registered once.");
            }

            $byPhase[$phase->value] = $handler;
        }

        foreach (InvariantFinalizationPhase::cases() as $required) {
            if (! isset($byPhase[$required->value])) {
                throw new InvalidArgumentException("Invariant finalization phase {$required->name} is required.");
            }
        }

        ksort($byPhase);
        $this->handlers = array_values($byPhase);
    }

    public function process(RunRequest $request, TurnContext $context, RunResult $result): RunResult
    {
        foreach ($this->handlers as $handler) {
            $result = $handler->handle($request, $context, $result);
        }

        $this->assertCanonical($result);

        return $result;
    }

    public function assertCanonical(RunResult $result): void
    {
        if ($result->requiredVerification === null) {
            throw new LogicException('Canonical finalization requires an explicit required-verification outcome.');
        }

        if ($result->status === RunStatus::Succeeded && $result->requiredVerification !== RequiredVerificationOutcome::Passed) {
            throw new LogicException('A canonical successful result requires passing independent verification.');
        }
    }
}
