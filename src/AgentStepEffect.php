<?php

declare(strict_types=1);

namespace Sifrious\Logres;

interface AgentStepEffect
{
    /**
     * Reconciles a prior effect by determination.operationIdentity, or performs
     * it once. Implementations must atomically fence that identity before an
     * external side effect and return the same observation after redelivery.
     *
     * Lifecycle changes must use ExecutionState/ExecutionStateService; this
     * port must not implement another Run/Attempt/Lease state machine.
     */
    public function reconcileOrPerform(AgentStepDetermination $determination): AgentStepObservation;
}
