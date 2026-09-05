<?php

declare(strict_types=1);

namespace Sifrious\Logres;

enum LoopHandoffType: string
{
    case Phase = 'phase';
    case Ticket = 'ticket';
}
