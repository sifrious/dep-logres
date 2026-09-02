<?php

declare(strict_types=1);

namespace Sifrious\Logres;

interface InvariantBeforeTurnHandler extends BeforeTurnHandler
{
    public function phase(): InvariantPreflightPhase;
}
