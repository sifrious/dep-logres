<?php

declare(strict_types=1);

namespace Sifrious\Logres;

enum RequiredVerificationOutcome: string
{
    case Passed = 'passed';
    case Failed = 'failed';
    case NotRun = 'not_run';
}
