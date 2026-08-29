<?php

declare(strict_types=1);

namespace Sifrious\Logres;

use DateTimeImmutable;
use InvalidArgumentException;

final readonly class CapabilitySnapshot
{
    /** @var list<string> */ public array $capabilities;
    /** @var list<string> */ public array $runtimeAdapters;
    /** @var list<string> */ public array $protocolVersions;

    public function __construct(array $capabilities, array $runtimeAdapters, array $protocolVersions, public DateTimeImmutable $observedAt)
    {
        $this->capabilities = self::strings($capabilities, 'capabilities');
        $this->runtimeAdapters = self::strings($runtimeAdapters, 'runtime adapters');
        $this->protocolVersions = self::strings($protocolVersions, 'protocol versions');
    }

    private static function strings(array $values, string $label): array
    {
        if ($values === [] || array_filter($values, static fn ($value): bool => ! is_string($value) || trim($value) === '') !== []) {
            throw new InvalidArgumentException("A capability snapshot requires non-empty {$label}.");
        }
        $values = array_values(array_unique($values));
        sort($values);
        return $values;
    }

    public function supports(RunnerCompatibilityRequirements $requirements): RunnerCompatibility
    {
        $failures = [];
        if (! in_array($requirements->runtimeAdapterProfile, $this->runtimeAdapters, true)) {
            $failures[] = RunnerCompatibilityFailure::RuntimeAdapterProfile;
        }
        if (! in_array($requirements->protocolVersion, $this->protocolVersions, true)) {
            $failures[] = RunnerCompatibilityFailure::ProtocolVersion;
        }
        if (array_diff($requirements->capabilities, $this->capabilities) !== []) {
            $failures[] = RunnerCompatibilityFailure::Capability;
        }

        return new RunnerCompatibility($failures);
    }
}
