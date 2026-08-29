<?php

declare(strict_types=1);

namespace Sifrious\Logres;

enum RunnerEventType: string
{
    case Accepted = 'accepted';
    case Starting = 'starting';
    case Running = 'running';
    case Status = 'status';
    case Output = 'output';
    case Question = 'question';
    case InterventionRequired = 'intervention_required';
    case ArtifactReference = 'artifact_reference';
    case Warning = 'warning';
    case Failure = 'failure';
    case TerminalResult = 'terminal_result';
}
