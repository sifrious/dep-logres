<?php

declare(strict_types=1);

namespace Sifrious\Logres;

use InvalidArgumentException;

final readonly class TaskPrompt
{
    public function __construct(
        public TaskPromptId $id,
        public TaskId $taskId,
        public ExecutionRequestId $requestId,
        public int $version,
        public string $compilerVersion,
        public string $compiledPrompt,
        public string $inputHash,
        public string $provenanceHash,
        public TaskPromptCompilationInput $input,
        public ?TaskPromptId $previousPromptId = null,
    ) {
        $expectedId = "prompt:{$taskId->value}:v{$version}";
        $expectedPreviousId = "prompt:{$taskId->value}:v".($version - 1);

        if ($version < 1 || $id->value !== $expectedId) {
            throw new InvalidArgumentException('Task prompt identity and version must agree.');
        }

        if ($input->task->id->value !== $taskId->value || $input->request->id->value !== $requestId->value) {
            throw new InvalidArgumentException('Task prompt identities must agree with the compilation input.');
        }

        if (trim($compilerVersion) === '' || trim($compiledPrompt) === '') {
            throw new InvalidArgumentException('Task prompt compiler identity and bytes are required.');
        }

        if (preg_match('/^[a-f0-9]{64}$/', $inputHash) !== 1 || preg_match('/^[a-f0-9]{64}$/', $provenanceHash) !== 1) {
            throw new InvalidArgumentException('Task prompt input and provenance identities must be SHA-256 hashes.');
        }

        if (($version === 1 && $previousPromptId !== null) || ($version > 1 && $previousPromptId?->value !== $expectedPreviousId)) {
            throw new InvalidArgumentException('Task prompt lineage must reference the preceding version.');
        }
    }
}
