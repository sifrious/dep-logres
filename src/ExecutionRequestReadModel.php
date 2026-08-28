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
        public array $requester,
        public string $channel,
        public string $relationship,
        public ?string $parentRequestId,
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
            requester: [
                'reference' => $request->requester->reference,
                'display_name' => $request->requester->displayName,
            ],
            channel: $request->channel->value,
            relationship: $request->relationship->value,
            parentRequestId: $request->parentRequestId?->value,
        );
    }
}
