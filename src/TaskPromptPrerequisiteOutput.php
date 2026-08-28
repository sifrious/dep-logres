<?php

declare(strict_types=1);

namespace Sifrious\Logres;

final readonly class TaskPromptPrerequisiteOutput
{
    public string $contentHash;

    public function __construct(
        public TaskId $taskId,
        public string $content,
    ) {
        $this->contentHash = hash('sha256', $content);
    }

    public function canonicalData(): array
    {
        return [
            'content' => $this->content,
            'content_hash' => $this->contentHash,
            'task_id' => $this->taskId->value,
        ];
    }
}
