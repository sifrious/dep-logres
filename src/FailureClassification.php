<?php

declare(strict_types=1);

namespace Sifrious\Logres;

enum FailureClassification: string
{
    case Transient = 'transient';
    case Permanent = 'permanent';
    case AcknowledgementUncertain = 'acknowledgement_uncertain';
}
