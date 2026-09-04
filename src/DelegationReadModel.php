<?php

declare(strict_types=1);

namespace Sifrious\Logres;

use InvalidArgumentException;

final readonly class DelegationReadModel
{
    public function __construct(
        public string $delegationId,
        public string $parentRunId,
        public string $parentAttemptId,
        public string $childRunId,
        public string $childRequestId,
        public string $agentId,
        public string $agentVersion,
        public int $depth,
        public string $policyVersion,
        public string $status,
        public ?array $currentAttempt,
        public array $attempts,
        public ?array $needsInput,
        public ?string $terminalResultReference,
        public ?string $failureReason,
    ) {}

    public static function fromCanonicalState(
        DelegationRequest $delegation,
        ExecutionState $child,
        ?InputRequestReference $needsInput = null,
    ): self {
        if ($child->runId->value !== $delegation->childRunId->value) {
            throw new InvalidArgumentException('Delegation projection requires the canonical state of its child Run.');
        }
        if (($child->status === RunStatus::NeedsInput) !== ($needsInput !== null)) {
            throw new InvalidArgumentException('NeedsInput evidence must be present exactly while the child Run needs input.');
        }

        $state = ExecutionStateReadModel::fromState($child);

        return new self(
            $delegation->id->value,
            $delegation->parentRunId->value,
            $delegation->parentAttemptId->value,
            $delegation->childRunId->value,
            $delegation->childRequestId->value,
            $delegation->agent->id,
            $delegation->agent->version,
            $delegation->authorization->childDepth,
            $delegation->authorization->policyVersion,
            $state->status,
            $state->currentAttempt,
            $state->attempts,
            $needsInput?->toArray(),
            $state->terminalResultReference,
            $state->failureReason,
        );
    }
}
