<?php

declare(strict_types=1);

namespace Sifrious\Logres;

enum RunnerPollResponseStatus: string
{
    case Lease = 'lease';
    case NoWork = 'no_work';
}
