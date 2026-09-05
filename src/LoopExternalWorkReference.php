<?php

declare(strict_types=1);

namespace Sifrious\Logres;

use InvalidArgumentException;

/**
 * Records an authorized, replay-safe adapter mapping; it does not perform the external write.
 */
final readonly class LoopExternalWorkReference
{
    public string $idempotencyIdentity;

    public function __construct(
        public TaskId $taskId,
        public string $provider,
        public string $externalIdentifier,
        public string $authorizationReference,
    ) {
        foreach ([$provider, $externalIdentifier, $authorizationReference] as $value) {
            if (trim($value) === '') {
                throw new InvalidArgumentException('External work mappings require provider, identifier, authorization, and idempotency references.');
            }
        }

        $this->idempotencyIdentity = 'external-work:'.hash(
            'sha256',
            strtolower(trim($provider))."\0".$taskId->value,
        );
    }
}
