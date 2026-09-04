<?php

declare(strict_types=1);

namespace Sifrious\Logres;

enum LoopCheckpointType: string
{
    case ArchitecturePlacement = 'architecture_placement';
    case SimplicityCut = 'simplicity_cut';
}
