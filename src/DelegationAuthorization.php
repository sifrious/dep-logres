<?php

declare(strict_types=1);

namespace Sifrious\Logres;

use InvalidArgumentException;

/**
 * Immutable proof that the external run policy allowed one child creation.
 *
 * Policy evaluation belongs to MME-1010; this value only freezes its allowed
 * decision so delegation cannot rely on ambient or subsequently changed limits.
 */
final readonly class DelegationAuthorization
{
    public function __construct(
        public string $decisionId,
        public string $policyVersion,
        public int $childDepth,
        public int $maximumDepth,
        public int $activeChildrenBefore,
        public int $maximumConcurrentChildren,
        public string $authorizedAt,
    ) {
        if (preg_match('/^delegation-decision:[a-zA-Z0-9._-]+$/', $decisionId) !== 1
            || trim($policyVersion) === ''
            || $childDepth < 1
            || $maximumDepth < $childDepth
            || $activeChildrenBefore < 0
            || $maximumConcurrentChildren < 1
            || $activeChildrenBefore >= $maximumConcurrentChildren
            || preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d+)?Z$/', $authorizedAt) !== 1) {
            throw new InvalidArgumentException('Delegation authorization must preserve an allowed, bounded, versioned policy decision.');
        }
    }
}
