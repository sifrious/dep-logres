<?php

declare(strict_types=1);

namespace Sifrious\Logres;

use InvalidArgumentException;

final readonly class TaskPromptCompiler
{
    public function __construct(public string $compilerVersion = 'logres-task-prompt-v1')
    {
        if (trim($compilerVersion) === '') {
            throw new InvalidArgumentException('A task prompt compiler version is required.');
        }
    }

    public function compile(TaskPromptCompilationInput $input, ?TaskPrompt $latest = null): TaskPrompt
    {
        if ($latest !== null && $latest->taskId->value !== $input->task->id->value) {
            throw new InvalidArgumentException('A task prompt can only advance the same task lineage.');
        }

        $canonicalInput = $this->encode($input->canonicalData());
        $inputHash = hash('sha256', $canonicalInput);

        if ($latest !== null && $latest->inputHash === $inputHash && $latest->compilerVersion === $this->compilerVersion) {
            return $latest;
        }

        $version = $latest === null ? 1 : $latest->version + 1;
        $compiledPrompt = "# Task execution prompt\n\n".$canonicalInput."\n";
        $provenanceHash = hash('sha256', $this->compilerVersion."\n".$inputHash."\n".$compiledPrompt);

        return new TaskPrompt(
            id: new TaskPromptId("prompt:{$input->task->id->value}:v{$version}"),
            taskId: $input->task->id,
            requestId: $input->request->id,
            version: $version,
            compilerVersion: $this->compilerVersion,
            compiledPrompt: $compiledPrompt,
            inputHash: $inputHash,
            provenanceHash: $provenanceHash,
            input: $input,
            previousPromptId: $latest?->id,
        );
    }

    private function encode(array $data): string
    {
        return json_encode($this->canonicalize($data), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }

    private function canonicalize(array $data): array
    {
        if (array_is_list($data)) {
            return array_map(fn (mixed $value): mixed => is_array($value) ? $this->canonicalize($value) : $value, $data);
        }

        ksort($data);

        return array_map(fn (mixed $value): mixed => is_array($value) ? $this->canonicalize($value) : $value, $data);
    }
}
