<?php

declare(strict_types=1);

namespace Sifrious\Logres;

enum RequiredVerificationOutcome: string
{
    case Passed = 'passed';
    case Failed = 'failed';
    case Incomplete = 'incomplete';
    case SkippedByPolicy = 'skipped_by_policy';
    case Unavailable = 'unavailable';
    case NotRun = 'not_run';
}
