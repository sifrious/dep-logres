<?php

declare(strict_types=1);

namespace Sifrious\Logres;

final readonly class ExecutionRequest
{
    public array $attachments;

    public function __construct(
        public ExecutionRequestId $id,
        public string $originalPrompt,
        public ExecutionContext $context,
        public string $desiredResult,
        array $attachments,
        public ExecutionConstraints $constraints,
        public ExecutionPermissions $permissions,
        public RequesterIdentity $requester,
        public DeliveryChannel $channel,
        public RequestRelationship $relationship = RequestRelationship::Original,
        public ?ExecutionRequestId $parentRequestId = null,
    ) {
        $this->attachments = array_values($attachments);
    }
}
