<?php

declare(strict_types=1);

namespace Sifrious\Logres;

use InvalidArgumentException;
use Sifrious\Wardrobe\RuntimeAdapter;
use Sifrious\Wardrobe\RuntimeInvocation;

/** The sole concrete runtime invocation path from Logres runner orchestration. */
final readonly class WardrobeRunnerRuntime implements RunnerRuntime
{
    /** @var array<string, RuntimeAdapter> */ private array $adapters;

    /** @param array<string, RuntimeAdapter> $adapters */
    public function __construct(array $adapters)
    {
        foreach ($adapters as $name => $_adapter) {
            if (trim($name) === '') {
                throw new InvalidArgumentException('Wardrobe adapters require non-empty names.');
            }
        }
        $this->adapters = $adapters;
    }

    public function availableAdapters(): array
    {
        $names = array_keys($this->adapters);
        sort($names);
        return $names;
    }

    public function canInvoke(string $adapter, string $runtime): bool
    {
        return isset($this->adapters[$adapter]) && $this->adapters[$adapter]->supports($runtime);
    }

    public function invoke(RuntimeRequest $request, RunnerRuntimeObserver $observer): RuntimeResult
    {
        $adapter = $this->adapters[$request->adapter] ?? throw new InvalidArgumentException('The selected Wardrobe adapter is unavailable.');
        if (! $adapter->supports($request->runtime)) {
            throw new InvalidArgumentException('The selected Wardrobe adapter does not support the requested runtime.');
        }

        $prompt = $request->payload['prompt'] ?? null;
        $timeout = $request->payload['timeout_seconds'] ?? 3600;
        $permissions = $request->payload['permissions'] ?? [];
        if (! is_string($prompt) || trim($prompt) === '' || ! is_int($timeout) || $timeout < 1 || ! is_array($permissions)) {
            throw new InvalidArgumentException('Wardrobe invocation payload requires prompt, positive timeout_seconds, and permissions.');
        }

        $outcome = $adapter->invoke(
            new RuntimeInvocation($request->runId->value, $request->runtime, $request->workspacePath->value, $prompt, $timeout, $permissions),
            new WardrobeRunnerObserver($observer),
        );

        $status = match ($outcome->status) {
            'success', 'succeeded' => RunnerTerminalStatus::Success,
            'cancelled' => RunnerTerminalStatus::Cancelled,
            'timed_out' => RunnerTerminalStatus::TimedOut,
            default => RunnerTerminalStatus::Failure,
        };

        return new RuntimeResult($status, $outcome->exitCode, $status === RunnerTerminalStatus::Failure ? 'runtime_outcome' : null, $outcome->reason);
    }
}
