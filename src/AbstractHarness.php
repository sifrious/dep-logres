<?php

declare(strict_types=1);

namespace Sifrious\Logres;

use InvalidArgumentException;
use UnexpectedValueException;

abstract class AbstractHarness implements HarnessInterface
{
    public function __construct(
        private readonly string $harnessId,
        private readonly HarnessCapability $harnessCapabilities,
    ) {
        if (! self::isValidId($this->harnessId)) {
            throw new InvalidArgumentException('A harness ID must be a stable lowercase identifier.');
        }
    }

    final public function id(): string
    {
        return $this->harnessId;
    }

    final public function capabilities(): HarnessCapability
    {
        return $this->harnessCapabilities;
    }

    final public function start(RunRequest $request, TurnContext $context, ExecutionObserver $observer): HarnessHandle
    {
        if ($request->harnessId !== $this->id()) {
            throw new InvalidArgumentException("Run request targets {$request->harnessId}, not {$this->id()}.");
        }

        $handle = $this->startHarness($request, $context, $observer);

        if ($handle->harnessId !== $this->id()) {
            throw new UnexpectedValueException('A harness handle must retain the registered harness ID.');
        }

        return $handle;
    }

    abstract protected function startHarness(RunRequest $request, TurnContext $context, ExecutionObserver $observer): HarnessHandle;

    private static function isValidId(string $id): bool
    {
        return preg_match('/^[a-z][a-z0-9._-]*$/', $id) === 1;
    }
}
