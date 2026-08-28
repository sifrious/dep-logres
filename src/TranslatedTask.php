<?php

declare(strict_types=1);

namespace Sifrious\Logres;

final readonly class TranslatedTask
{
    public array $acceptanceEvidence;

    public array $dependencies;

    public array $readinessConditions;

    public function __construct(
        public TaskId $id,
        public ExecutionRequestId $requestId,
        public string $objective,
        public string $expectedOutput,
        array $acceptanceEvidence,
        array $dependencies,
        array $readinessConditions,
        public bool $canRunConcurrently,
        public bool $mayRequireHumanInput,
        public string $target,
        public string $agent,
        public TaskStatus $status = TaskStatus::Planned,
    ) {
        $this->acceptanceEvidence = array_values($acceptanceEvidence);
        $this->dependencies = array_values($dependencies);
        $this->readinessConditions = array_values($readinessConditions);
    }

    public function withStatus(TaskStatus $status): self
    {
        return new self(
            id: $this->id,
            requestId: $this->requestId,
            objective: $this->objective,
            expectedOutput: $this->expectedOutput,
            acceptanceEvidence: $this->acceptanceEvidence,
            dependencies: $this->dependencies,
            readinessConditions: $this->readinessConditions,
            canRunConcurrently: $this->canRunConcurrently,
            mayRequireHumanInput: $this->mayRequireHumanInput,
            target: $this->target,
            agent: $this->agent,
            status: $status,
        );
    }
}
