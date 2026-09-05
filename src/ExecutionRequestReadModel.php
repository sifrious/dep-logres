<?php

declare(strict_types=1);

namespace Sifrious\Logres;

final readonly class ExecutionRequestReadModel
{
    public function __construct(
        public string $id,
        public string $originalPrompt,
        public ?string $projectReference,
        public ?string $repositoryReference,
        public string $desiredResult,
        public array $attachments,
        public array $constraints,
        public array $permissions,
        public ?array $authorization,
        public string $channel,
        public string $relationship,
        public ?string $parentRequestId,
        public ?array $origin = null,
        public array $executionIdentity = [],
    ) {}

    public static function fromRequest(ExecutionRequest $request): self
    {
        return new self(
            id: $request->id->value,
            originalPrompt: $request->originalPrompt,
            projectReference: $request->context->projectReference,
            repositoryReference: $request->context->repositoryReference,
            desiredResult: $request->desiredResult,
            attachments: array_map(
                static fn (ExecutionAttachment $attachment): array => [
                    'reference' => $attachment->reference,
                    'name' => $attachment->name,
                ],
                $request->attachments,
            ),
            constraints: [
                'timeout_seconds' => $request->constraints->timeoutSeconds,
                'writable_paths' => $request->constraints->writablePaths,
            ],
            permissions: [
                'network' => $request->permissions->network,
                'filesystem_write' => $request->permissions->filesystemWrite,
                'external_communication' => $request->permissions->externalCommunication,
            ],
            authorization: $request->authorization?->toArray(),
            channel: $request->channel->value,
            relationship: $request->relationship->value,
            parentRequestId: $request->parentRequestId?->value,
            origin: $request->origin === null ? null : [
                'user_input' => $request->origin->userInputReference,
                'intent' => $request->origin->intentReference,
                'conversation' => $request->origin->conversationReference,
                'plan' => $request->origin->planReference,
                'plan_step' => $request->origin->planStepReference,
            ],
            executionIdentity: $request->executionIdentity?->toArray()
                ?? ExecutionProvenanceClassification::missingRecord(),
        );
    }
}
