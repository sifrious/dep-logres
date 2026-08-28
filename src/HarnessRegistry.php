<?php

declare(strict_types=1);

namespace Sifrious\Logres;

use InvalidArgumentException;
use OutOfBoundsException;

final class HarnessRegistry
{
    private array $harnesses = [];

    public function __construct(iterable $harnesses = [])
    {
        foreach ($harnesses as $harness) {
            $this->register($harness);
        }
    }

    public function register(HarnessInterface $harness): void
    {
        $id = $harness->id();

        if (preg_match('/^[a-z][a-z0-9._-]*$/', $id) !== 1) {
            throw new InvalidArgumentException('A harness ID must be a stable lowercase identifier.');
        }

        if (isset($this->harnesses[$id])) {
            throw new InvalidArgumentException("Harness {$id} is already registered.");
        }

        $this->harnesses[$id] = $harness;
        ksort($this->harnesses);
    }

    public function get(string $id): HarnessInterface
    {
        return $this->harnesses[$id] ?? throw new OutOfBoundsException("Harness {$id} is not registered.");
    }

    public function all(): array
    {
        return array_values($this->harnesses);
    }
}
