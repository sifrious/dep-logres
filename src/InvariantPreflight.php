<?php

declare(strict_types=1);

namespace Sifrious\Logres;

use InvalidArgumentException;

/**
 * Sealed production preflight. Every required phase is present exactly once and
 * runs in package-owned order before optional BeforeTurn contributors.
 */
final readonly class InvariantPreflight
{
    /** @var list<InvariantBeforeTurnHandler> */
    private array $handlers;

    public function __construct(iterable $handlers)
    {
        $byPhase = [];

        foreach ($handlers as $handler) {
            if (! $handler instanceof InvariantBeforeTurnHandler) {
                throw new InvalidArgumentException('Every invariant preflight handler must implement InvariantBeforeTurnHandler.');
            }

            $phase = $handler->phase();
            if (isset($byPhase[$phase->value])) {
                throw new InvalidArgumentException("Invariant preflight phase {$phase->name} may only be registered once.");
            }

            $byPhase[$phase->value] = $handler;
        }

        foreach (InvariantPreflightPhase::cases() as $required) {
            if (! isset($byPhase[$required->value])) {
                throw new InvalidArgumentException("Invariant preflight phase {$required->name} is required.");
            }
        }

        ksort($byPhase);
        $this->handlers = array_values($byPhase);
    }

    public function process(RunRequest $request, TurnContext $context): TurnContext
    {
        foreach ($this->handlers as $handler) {
            $context = $handler->handle($request, $context);
        }

        return $context;
    }
}
