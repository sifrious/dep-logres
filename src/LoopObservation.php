<?php

declare(strict_types=1);

namespace Sifrious\Logres;

use DateTimeImmutable;
use InvalidArgumentException;

final readonly class LoopObservation
{
    /** @param list<EvidenceReference> $evidence */
    public function __construct(
        public DateTimeImmutable $observedAt,
        public LoopOperation $operation,
        public int $steps,
        public int $attempts,
        public int $toolCalls,
        public int $consecutiveFailures,
        public int $delegationDepth,
        public int $concurrentChildren,
        public ?int $tokensUsed = null,
        public ?int $costMicrosUsed = null,
        public ?DateTimeImmutable $needsInputSince = null,
        public bool $cancellationRequested = false,
        public bool $authorizationActive = true,
        public bool $completionClaimed = false,
        public RequiredVerificationOutcome $verification = RequiredVerificationOutcome::NotRun,
        public array $evidence = [],
    ) {
        foreach ([$steps, $attempts, $toolCalls, $consecutiveFailures, $delegationDepth, $concurrentChildren] as $count) {
            if ($count < 0) {
                throw new InvalidArgumentException('Loop observations cannot contain negative counters.');
            }
        }
        if (($tokensUsed !== null && $tokensUsed < 0) || ($costMicrosUsed !== null && $costMicrosUsed < 0)) {
            throw new InvalidArgumentException('Observable provider usage cannot be negative.');
        }
        if ($needsInputSince !== null && $needsInputSince > $observedAt) {
            throw new InvalidArgumentException('Input cannot have been requested after the observation.');
        }
        foreach ($evidence as $reference) {
            if (! $reference instanceof EvidenceReference) {
                throw new InvalidArgumentException('Loop evidence must use canonical EvidenceReference values.');
            }
        }
    }
}
