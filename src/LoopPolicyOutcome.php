<?php

declare(strict_types=1);

namespace Sifrious\Logres;

enum LoopPolicyOutcome: string
{
    case Continue = 'continue';
    case AwaitingInput = 'awaiting_input';
    case SuccessfulCompletion = 'successful_completion';
    case PolicyExhausted = 'policy_exhausted';
    case UnresolvedNeedsInput = 'unresolved_needs_input';
    case Cancelled = 'cancelled';
    case AuthorizationRevoked = 'authorization_revoked';

    public function isTerminal(): bool
    {
        return ! in_array($this, [self::Continue, self::AwaitingInput], true);
    }
}
