<?php

declare(strict_types=1);

namespace Sifrious\Logres;

use InvalidArgumentException;

final readonly class TaskPromptReadModel
{
    public function __construct(
        public string $id,
        public string $taskId,
        public int $version,
        public string $compilerVersion,
        public string $compiledPrompt,
        public string $inputHash,
        public string $provenanceHash,
        public array $contextSources,
        public array $skills,
        public array $tools,
        public array $permissions,
        public array $allowedOperations,
        public array $resultContract,
        public array $reportingContract,
        public array $versions,
    ) {}

    public static function fromVersions(array $versions): self
    {
        if ($versions === []) {
            throw new InvalidArgumentException('A task prompt read model requires at least one version.');
        }

        usort($versions, static fn (TaskPrompt $left, TaskPrompt $right): int => $left->version <=> $right->version);
        $latest = $versions[array_key_last($versions)];
        $input = $latest->input;

        return new self(
            id: $latest->id->value,
            taskId: $latest->taskId->value,
            version: $latest->version,
            compilerVersion: $latest->compilerVersion,
            compiledPrompt: $latest->compiledPrompt,
            inputHash: $latest->inputHash,
            provenanceHash: $latest->provenanceHash,
            contextSources: array_map(static fn (TaskPromptContextSource $source): array => [
                'id' => $source->id,
                'kind' => $source->kind,
                'label' => $source->label,
                'content_hash' => $source->contentHash,
            ], $input->contextSources),
            skills: array_map(static fn (TaskPromptSkill $skill): array => $skill->canonicalData(), $input->skills),
            tools: array_map(static fn (TaskPromptTool $tool): array => $tool->canonicalData(), $input->tools),
            permissions: ExecutionRequestReadModel::fromRequest($input->request)->permissions,
            allowedOperations: $input->allowedOperations,
            resultContract: $input->resultContract->canonicalData(),
            reportingContract: $input->reportingContract->canonicalData(),
            versions: array_map(static fn (TaskPrompt $prompt): array => [
                'id' => $prompt->id->value,
                'version' => $prompt->version,
                'compiler_version' => $prompt->compilerVersion,
                'compiled_prompt' => $prompt->compiledPrompt,
                'input_hash' => $prompt->inputHash,
                'provenance_hash' => $prompt->provenanceHash,
                'previous_prompt_id' => $prompt->previousPromptId?->value,
            ], $versions),
        );
    }
}
