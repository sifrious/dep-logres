<?php

declare(strict_types=1);

namespace Sifrious\Logres;

use DateTimeImmutable;
use InvalidArgumentException;

final readonly class ExecutionStateDetails
{
    /** @param array<string, int|float|string|bool|null>|null $diffStats */
    public function __construct(
        public string $title,
        public string $prompt,
        public string $createdByUserId,
        public ?string $workspaceId = null,
        public ?string $parentTaskId = null,
        public ?string $baseBranch = null,
        public ?string $branchName = null,
        public ?string $worktreePath = null,
        public ?string $sqlitePath = null,
        public ?int $pullRequestNumber = null,
        public ?string $pullRequestUrl = null,
        public ?array $diffStats = null,
        public ?string $outputLogPath = null,
        public ?string $approvedByUserId = null,
        public ?DateTimeImmutable $approvedAt = null,
        public ?string $runtimeInvocationId = null,
        public ?string $targetReference = null,
        public ?DateTimeImmutable $updatedAt = null,
    ) {
        if (trim($title) === '' || trim($prompt) === '' || trim($createdByUserId) === '') {
            throw new InvalidArgumentException('Execution details require a title, prompt, and creator identity.');
        }
    }

    public function approved(string $userId, DateTimeImmutable $at): self
    {
        if (trim($userId) === '') {
            throw new InvalidArgumentException('Approval requires a user identity.');
        }
        if ($this->approvedByUserId === $userId && $this->approvedAt == $at) {
            return $this;
        }
        return $this->copy(approvedByUserId: $userId, approvedAt: $at, updatedAt: $at);
    }

    /** @param array<string, int|float|string|bool|null>|null $diffStats */
    public function result(?int $pullRequestNumber, ?string $pullRequestUrl, ?array $diffStats, ?string $outputLogPath, DateTimeImmutable $at): self
    {
        return $this->copy($pullRequestNumber, $pullRequestUrl, $diffStats, $outputLogPath, updatedAt: $at);
    }

    /** @param array<string, int|float|string|bool|null>|null $diffStats */
    private function copy(?int $pullRequestNumber = null, ?string $pullRequestUrl = null, ?array $diffStats = null, ?string $outputLogPath = null, ?string $approvedByUserId = null, ?DateTimeImmutable $approvedAt = null, ?DateTimeImmutable $updatedAt = null): self
    {
        return new self($this->title, $this->prompt, $this->createdByUserId, $this->workspaceId, $this->parentTaskId, $this->baseBranch, $this->branchName, $this->worktreePath, $this->sqlitePath, $pullRequestNumber ?? $this->pullRequestNumber, $pullRequestUrl ?? $this->pullRequestUrl, $diffStats ?? $this->diffStats, $outputLogPath ?? $this->outputLogPath, $approvedByUserId ?? $this->approvedByUserId, $approvedAt ?? $this->approvedAt, $this->runtimeInvocationId, $this->targetReference, $updatedAt ?? $this->updatedAt);
    }
}
