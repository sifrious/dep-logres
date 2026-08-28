<?php

declare(strict_types=1);

namespace Sifrious\Logres;

enum TaskAction: string
{
    case Start = 'start';
    case Skip = 'skip';
    case Cancel = 'cancel';
    case Succeed = 'succeed';
    case Fail = 'fail';
    case WaitForInput = 'wait_for_input';
    case Retry = 'retry';
    case Replan = 'replan';
}
