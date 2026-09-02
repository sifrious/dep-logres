<?php

declare(strict_types=1);

namespace Sifrious\Logres;

use Sifrious\AuthorizationContract\AuthorizationContext;

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
        public ?AuthorizationContext $authorization,
        public DeliveryChannel $channel,
        public RequestRelationship $relationship = RequestRelationship::Original,
        public ?ExecutionRequestId $parentRequestId = null,
        public ?DeliberationOrigin $origin = null,
    ) {
        $this->attachments = array_values($attachments);
    }
}
