<?php

declare(strict_types=1);

namespace Sifrious\Logres;

enum ExecutionEventType: string
{
    case TargetAccepted = 'target_accepted';
    case AgentStarted = 'agent_started';
    case AgentMessage = 'agent_message';
    case Progress = 'progress';
    case ToolInvoked = 'tool_invoked';
    case ToolCompleted = 'tool_completed';
    case CommandExecuted = 'command_executed';
    case FileChanged = 'file_changed';
    case TestStarted = 'test_started';
    case TestCompleted = 'test_completed';
    case ArtifactProduced = 'artifact_produced';
    case Warning = 'warning';
    case InputRequested = 'input_requested';
    case TaskCompleted = 'task_completed';
    case TaskFailed = 'task_failed';
    case TaskTimedOut = 'task_timed_out';
    case TaskCancelled = 'task_cancelled';
}
