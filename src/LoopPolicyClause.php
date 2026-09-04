<?php

declare(strict_types=1);

namespace Sifrious\Logres;

enum LoopPolicyClause: string
{
    case WithinBudgets = 'continue.within_budgets';
    case AwaitInput = 'input.await';
    case NeedsInputUnresolved = 'input.unresolved';
    case VerificationUnavailable = 'completion.verification_unavailable';
    case SuccessfulCompletion = 'completion.verified';
    case CancellationRequested = 'termination.cancellation_requested';
    case AuthorizationRevoked = 'termination.authorization_revoked';
    case WallClockDeadline = 'budget.wall_clock_deadline';
    case MaximumSteps = 'budget.maximum_steps';
    case MaximumAttempts = 'budget.maximum_attempts';
    case MaximumToolCalls = 'budget.maximum_tool_calls';
    case MaximumConsecutiveFailures = 'budget.maximum_consecutive_failures';
    case MaximumTokens = 'budget.maximum_tokens';
    case MaximumCost = 'budget.maximum_cost';
    case MaximumDelegationDepth = 'budget.maximum_delegation_depth';
    case MaximumConcurrentChildren = 'budget.maximum_concurrent_children';
}
