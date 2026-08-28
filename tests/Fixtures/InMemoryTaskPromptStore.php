<?php

declare(strict_types=1);

namespace Sifrious\Logres\Tests\Fixtures;

use Sifrious\Logres\TaskId;
use Sifrious\Logres\TaskPrompt;
use Sifrious\Logres\TaskPromptId;
use Sifrious\Logres\TaskPromptStore;

final class InMemoryTaskPromptStore implements TaskPromptStore
{
    private array $prompts = [];

    public function save(TaskPrompt $prompt): void
    {
        $this->prompts[$prompt->id->value] ??= $prompt;
    }

    public function find(TaskPromptId $id): ?TaskPrompt
    {
        return $this->prompts[$id->value] ?? null;
    }

    public function latestForTask(TaskId $taskId): ?TaskPrompt
    {
        $versions = $this->versionsForTask($taskId);

        return $versions === [] ? null : $versions[array_key_last($versions)];
    }

    public function versionsForTask(TaskId $taskId): array
    {
        $versions = array_values(array_filter(
            $this->prompts,
            static fn (TaskPrompt $prompt): bool => $prompt->taskId->value === $taskId->value,
        ));
        usort($versions, static fn (TaskPrompt $left, TaskPrompt $right): int => $left->version <=> $right->version);

        return $versions;
    }
}
