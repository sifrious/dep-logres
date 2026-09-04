<?php

declare(strict_types=1);

namespace Sifrious\Logres;

enum LoopOperation: string
{
    case Observe = 'observe';
    case AdvanceStep = 'advance_step';
    case StartAttempt = 'start_attempt';
    case InvokeTool = 'invoke_tool';
    case Delegate = 'delegate';
    case SpawnChild = 'spawn_child';
}
