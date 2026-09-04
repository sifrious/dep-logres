<?php

declare(strict_types=1);

namespace Sifrious\Logres;

use Sifrious\Elwin\Handoff\ResumableHandoff;

final class HumanGate
{
    public static function pause(ResumableHandoff $handoff): never
    {
        throw new NeedsInput($handoff);
    }
}
