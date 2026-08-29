<?php

declare(strict_types=1);

namespace Sifrious\Logres;

final class RunTransitionPolicy
{
    public static function allows(RunStatus $from, RunStatus $to): bool
    {
        return in_array($to, self::allowedFrom($from), true);
    }

    public static function assertAllowed(RunStatus $from, RunStatus $to): void
    {
        if (! self::allows($from, $to)) {
            throw InvalidRunTransition::between($from, $to);
        }
    }

    public static function allowedFrom(RunStatus $status): array
    {
        return match ($status) {
            RunStatus::Pending => [RunStatus::Preparing, RunStatus::TimedOut, RunStatus::Cancelled],
            RunStatus::Preparing => [RunStatus::Running, RunStatus::Reconciling, RunStatus::NeedsInput, RunStatus::Failed, RunStatus::TimedOut, RunStatus::Cancelled],
            RunStatus::Running => [RunStatus::Reconciling, RunStatus::NeedsInput, RunStatus::Succeeded, RunStatus::Failed, RunStatus::TimedOut, RunStatus::Cancelled],
            RunStatus::Reconciling => [RunStatus::Preparing, RunStatus::Running, RunStatus::Failed, RunStatus::TimedOut, RunStatus::Cancelled],
            RunStatus::NeedsInput => [RunStatus::Preparing, RunStatus::Failed, RunStatus::TimedOut, RunStatus::Cancelled],
            RunStatus::Succeeded, RunStatus::Failed, RunStatus::TimedOut, RunStatus::Cancelled => [],
        };
    }
}
