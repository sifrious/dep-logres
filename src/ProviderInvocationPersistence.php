<?php

declare(strict_types=1);

namespace Sifrious\Logres;

/** Host transaction boundary for a Run and its provider invocation record. */
interface ProviderInvocationPersistence
{
    /** Atomically reserves invocation/idempotency identity and persists the awaiting Run. */
    public function reserve(Run $awaitingRun, ProviderInvocationRequest $request): ProviderInvocationReservation;

    /** Atomically persists both sides only when the prior invocation status and version still match. */
    public function transition(ProviderInvocationRecord $record, Run $run, ProviderInvocationStatus $expectedStatus, int $expectedVersion): bool;

    public function findRun(RunId $runId): ?Run;

    public function findRunByProviderExecutionId(ProviderExecutionId $providerExecutionId): ?Run;

    public function findInvocation(string $invocationId): ?ProviderInvocationRecord;

    public function findInvocationByIdempotencyKey(string $idempotencyKey): ?ProviderInvocationRecord;

    public function findInvocationByRunId(RunId $runId): ?ProviderInvocationRecord;
}
