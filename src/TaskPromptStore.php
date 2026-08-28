<?php

declare(strict_types=1);

namespace Sifrious\Logres;

interface TaskPromptStore
{
    public function save(TaskPrompt $prompt): void;

    public function find(TaskPromptId $id): ?TaskPrompt;

    public function latestForTask(TaskId $taskId): ?TaskPrompt;

    public function versionsForTask(TaskId $taskId): array;
}
