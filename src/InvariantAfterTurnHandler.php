<?php

declare(strict_types=1);

namespace Sifrious\Logres;

interface InvariantAfterTurnHandler extends AfterTurnHandler
{
    public function phase(): InvariantFinalizationPhase;
}
