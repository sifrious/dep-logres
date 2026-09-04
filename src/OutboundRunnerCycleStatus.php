<?php

declare(strict_types=1);

namespace Sifrious\Logres;

enum OutboundRunnerCycleStatus: string
{
    case NoWork = 'no_work';
    case RejectedAck = 'rejected_ack';
    case Completed = 'completed';
    case ReportRetry = 'report_retry';
}
