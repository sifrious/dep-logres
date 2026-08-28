<?php

declare(strict_types=1);

namespace Sifrious\Logres;

interface TaskPlanner
{
    public function plan(ExecutionRequest $request): TaskPlanningResult;

    public function replan(ExecutionRequest $request, TaskPlan $previous): TaskPlanningResult;
}
