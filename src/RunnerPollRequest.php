<?php

declare(strict_types=1);

namespace Sifrious\Logres;

use DateTimeImmutable;
use InvalidArgumentException;

final readonly class RunnerPollRequest
{
    /** @var list<string> */
    public array $protocolVersions;

    /** @var list<string> */
    public array $runtimeAdapters;

    /**
     * @param list<string> $protocolVersions
     * @param list<string> $runtimeAdapters
     */
    public function __construct(
        public RunnerIdentity $runnerId,
        public string $authenticationMaterial,
        array $protocolVersions,
        array $runtimeAdapters,
        public DateTimeImmutable $observedAt,
    ) {
        if (trim($authenticationMaterial) === '') {
            throw new InvalidArgumentException('Outbound polling requires authentication material.');
        }

        $this->protocolVersions = self::normalizeDimension($protocolVersions, 'protocol versions');
        $this->runtimeAdapters = self::normalizeDimension($runtimeAdapters, 'runtime adapters');
    }

    /**
     * @param list<string> $values
     * @return list<string>
     */
    private static function normalizeDimension(array $values, string $name): array
    {
        $normalized = [];
        foreach ($values as $value) {
            if (! is_string($value) || trim($value) === '') {
                throw new InvalidArgumentException("Runner poll {$name} must be non-empty strings.");
            }
            $normalized[] = $value;
        }
        if ($normalized === []) {
            throw new InvalidArgumentException("Runner poll {$name} cannot be empty.");
        }

        $normalized = array_values(array_unique($normalized));
        sort($normalized);

        return $normalized;
    }
}
