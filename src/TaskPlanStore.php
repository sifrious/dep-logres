<?php

declare(strict_types=1);

namespace Sifrious\Logres;

interface TaskPlanStore
{
    public function save(TaskPlan $plan): void;

    public function find(TaskPlanId $id): ?TaskPlan;

    public function findForRequest(ExecutionRequestId $requestId): ?TaskPlan;
}
