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
    public string $version;

    public function __construct(array $capabilities, array $runtimeAdapters, array $protocolVersions, public DateTimeImmutable $observedAt, ?string $version = null)
    {
        $this->capabilities = self::strings($capabilities, 'capabilities');
        $this->runtimeAdapters = self::strings($runtimeAdapters, 'runtime adapters');
        $this->protocolVersions = self::strings($protocolVersions, 'protocol versions');
        $computed = 'capability-snapshot:'.hash('sha256', json_encode([
            'capabilities' => $this->capabilities,
            'runtime_adapters' => $this->runtimeAdapters,
            'protocol_versions' => $this->protocolVersions,
            'observed_at' => $observedAt->format('Y-m-d\TH:i:s.u\Z'),
        ], JSON_THROW_ON_ERROR));
        if ($version !== null && ! hash_equals($computed, $version)) {
            throw new InvalidArgumentException('Capability snapshot version must match its canonical observed content.');
        }
        $this->version = $computed;
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
        if ($requirements->evaluatedAt !== null && $this->staleAt($requirements->evaluatedAt, $requirements->maximumSnapshotAgeSeconds)) {
            $failures[] = RunnerCompatibilityFailure::StaleCapabilitySnapshot;
        }

        return new RunnerCompatibility($failures);
    }

    public function staleAt(DateTimeImmutable $evaluatedAt, int $maximumAgeSeconds): bool
    {
        if ($maximumAgeSeconds < 0) {
            throw new InvalidArgumentException('Maximum capability snapshot age cannot be negative.');
        }
        $age = $evaluatedAt->getTimestamp() - $this->observedAt->getTimestamp();
        return $age < 0 || $age > $maximumAgeSeconds;
    }
}
