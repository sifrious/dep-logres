<?php

declare(strict_types=1);

namespace Sifrious\Logres;

use DateTimeImmutable;
use InvalidArgumentException;

final readonly class AgentStepDetermination
{
    /**
     * @param array<string, bool|float|int|string|null> $inputFacts
     * @param list<EvidenceReference> $evidence
     */
    public function __construct(
        public AgentStepId $stepId,
        public RunId $runId,
        public AttemptId $attemptId,
        public int $sequence,
        public AgentStepAction $action,
        public int $expectedStateVersion,
        public DateTimeImmutable $observedAt,
        public string $rationale,
        public LoopPolicyDetermination $policyDetermination,
        public array $inputFacts = [],
        public array $evidence = [],
        public ?DateTimeImmutable $reenterAt = null,
    ) {
        if ($sequence < 1 || $expectedStateVersion < 0) {
            throw new InvalidArgumentException('Agent Step sequence and expected state version must be valid.');
        }
        if (trim($rationale) === '') {
            throw new InvalidArgumentException('Agent Step determination requires a rationale.');
        }
        if ($stepId->value !== AgentStepId::forSequence($runId, $attemptId, $sequence)->value) {
            throw new InvalidArgumentException('Agent Step identity must match its Run, Attempt, and sequence.');
        }
        if ($action === AgentStepAction::Wait && $reenterAt === null) {
            throw new InvalidArgumentException('A waiting Agent Step requires a durable re-entry time.');
        }
    }

    public function operationIdentity(): string
    {
        return 'agent-step-effect:sha256:'.hash('sha256', implode("\0", [
            $this->runId->value,
            $this->attemptId->value,
            $this->stepId->value,
            $this->action->value,
        ]));
    }

    public function fingerprint(): string
    {
        return hash('sha256', serialize([
            $this->stepId->value,
            $this->runId->value,
            $this->attemptId->value,
            $this->sequence,
            $this->action->value,
            $this->expectedStateVersion,
            $this->observedAt->format(DATE_ATOM),
            $this->rationale,
            $this->policyDetermination->toArray(),
            $this->inputFacts,
            array_map(static fn (EvidenceReference $reference): array => [
                'kind' => $reference->kind,
                'locator' => $reference->locator,
                'observed_at' => $reference->observedAt,
                'sequence' => $reference->sequence,
                'metadata' => $reference->metadata,
            ], $this->evidence),
            $this->reenterAt?->format(DATE_ATOM),
        ]));
    }
}
