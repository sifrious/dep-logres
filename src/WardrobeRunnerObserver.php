<?php

declare(strict_types=1);

namespace Sifrious\Logres;

use Sifrious\Wardrobe\ProviderRuntimeObserver;

final readonly class WardrobeRunnerObserver implements ProviderRuntimeObserver
{
    public function __construct(private RunnerRuntimeObserver $observer) {}

    public function event(string $type, array $payload = []): void
    {
        $canonical = RunnerEventType::tryFrom($type);
        $this->observer->event($canonical ?? RunnerEventType::Status, $canonical === null ? ['runtime_event' => $type, 'payload' => $payload] : $payload);
    }

    public function stdout(string $chunk): void
    {
        $this->observer->event(RunnerEventType::Output, ['stream' => 'stdout', 'chunk' => $chunk]);
    }

    public function stderr(string $chunk): void
    {
        $this->observer->event(RunnerEventType::Output, ['stream' => 'stderr', 'chunk' => $chunk]);
    }

    public function cancellationRequested(): bool
    {
        return $this->observer->cancellationRequested();
    }

    public function providerExecutionAcknowledged(string $providerExecutionId): void
    {
        $this->observer->event(RunnerEventType::Status, ['provider_execution_acknowledged' => $providerExecutionId]);
    }
}
