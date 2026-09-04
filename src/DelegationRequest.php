<?php

declare(strict_types=1);

namespace Sifrious\Logres;

use InvalidArgumentException;

/**
 * The durable parent-child edge created before a host schedules child work.
 */
final readonly class DelegationRequest
{
    public function __construct(
        public DelegationId $id,
        public string $operationId,
        public RunId $parentRunId,
        public AttemptId $parentAttemptId,
        public RunId $childRunId,
        public ExecutionRequestId $childRequestId,
        public OrbisAgentDefinition $agent,
        public DelegationContext $context,
        public DelegationAuthorization $authorization,
        public string $requestedAt,
    ) {
        if (preg_match('/^delegation-operation:[a-zA-Z0-9._-]+$/', $operationId) !== 1
            || $parentRunId->value === $childRunId->value
            || preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d+)?Z$/', $requestedAt) !== 1) {
            throw new InvalidArgumentException('A delegation requires distinct parent and child Runs, operation identity, and request time.');
        }
    }
}
