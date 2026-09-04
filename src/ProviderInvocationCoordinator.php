<?php

declare(strict_types=1);

namespace Sifrious\Logres;

use InvalidArgumentException;
use Throwable;

final readonly class ProviderInvocationCoordinator
{
    public function __construct(
        private ProviderInvocationPersistence $persistence,
        private ProviderDispatch $provider,
        private ProviderExecutionBinder $binder = new ProviderExecutionBinder,
    ) {}

    public function dispatch(Run $run, ProviderInvocationRequest $request, string $dispatchedAt): ProviderInvocationOutcome
    {
        $this->assertMatchesProvenance($run, $request);
        $existing = $this->persistence->findInvocation($request->invocationId)
            ?? $this->persistence->findInvocationByIdempotencyKey($request->idempotencyKey);
        if ($existing !== null) {
            return $this->replay($run, $request, $existing);
        }

        $awaiting = $run->awaitingAcknowledgement($dispatchedAt);
        $reservation = $this->persistence->reserve($awaiting, $request);
        if (! $reservation->acquired) {
            return $this->replay($run, $request, $reservation->record);
        }

        return $this->invoke($awaiting, $reservation->record);
    }

    public function reconcile(Run $run, ProviderExecutionLookup $provider): ProviderBindingResult
    {
        if (! in_array($run->providerBindingStatus, [ProviderBindingStatus::AcknowledgementUncertain, ProviderBindingStatus::ReconciliationRequired], true)) {
            return new ProviderBindingResult(ProviderBindingOutcome::Conflict, $run, new ProviderBindingFailure('run_not_reconcilable', 'Only an acknowledgement-uncertain Run can perform provider reconciliation.'));
        }

        $record = $this->persistence->findInvocationByRunId($run->id);
        if ($record === null || ! in_array($record->status, [ProviderInvocationStatus::Dispatching, ProviderInvocationStatus::AcknowledgementUncertain], true)) {
            return new ProviderBindingResult(ProviderBindingOutcome::Conflict, $run, new ProviderBindingFailure('invocation_not_reconcilable', 'The Run has no matching uncertain provider invocation.'));
        }

        return $this->reconcileLookup($run, $record, $provider->find($run));
    }

    private function reconcileLookup(Run $run, ProviderInvocationRecord $record, ProviderExecutionLookupResult $lookup, bool $retryOnCasLoss = true): ProviderBindingResult
    {
        $expectedStatus = $record->status;
        $expectedVersion = $record->version;
        $owner = $lookup->acknowledgement === null ? null : $this->persistence->findRunByProviderExecutionId($lookup->acknowledgement->providerExecutionId)?->id;
        $result = $this->binder->reconcile($run, $lookup, $owner);
        if (in_array($result->outcome, [ProviderBindingOutcome::Acknowledged, ProviderBindingOutcome::Duplicate], true)) {
            $acknowledgement = $lookup->acknowledgement ?? $record->acknowledgement;
            if ($acknowledgement === null) {
                throw new InvalidArgumentException('Successful reconciliation requires provider acknowledgement evidence.');
            }
            $record = $record->record(ProviderDispatchResult::accepted($acknowledgement));
        } elseif ($result->outcome === ProviderBindingOutcome::Conflict && $lookup->acknowledgement !== null) {
            $record = $record->record(ProviderDispatchResult::bindingConflict($lookup->acknowledgement, $result->failure->message));
        } else {
            $record = $record->record(ProviderDispatchResult::acknowledgementUncertain($result->failure->message));
        }
        if (! $this->persistence->transition($record, $result->run, $expectedStatus, $expectedVersion)) {
            $currentRecord = $this->persistence->findInvocation($record->request->invocationId) ?? $record;
            $currentRun = $this->persistence->findRun($run->id) ?? $run;
            if ($currentRun->providerBindingStatus === ProviderBindingStatus::Acknowledged) {
                return new ProviderBindingResult(ProviderBindingOutcome::Duplicate, $currentRun);
            }
            if ($retryOnCasLoss && $lookup->status === ProviderLookupStatus::Found
                && in_array($currentRun->providerBindingStatus, [ProviderBindingStatus::AcknowledgementUncertain, ProviderBindingStatus::ReconciliationRequired], true)
                && in_array($currentRecord->status, [ProviderInvocationStatus::Dispatching, ProviderInvocationStatus::AcknowledgementUncertain], true)) {
                return $this->reconcileLookup($currentRun, $currentRecord, $lookup, false);
            }

            return new ProviderBindingResult(ProviderBindingOutcome::ReconciliationRequired, $currentRun, new ProviderBindingFailure('concurrent_reconciliation', 'Another reconciliation transition won; current durable state was retained.'));
        }

        return $result;
    }

    private function invoke(Run $awaiting, ProviderInvocationRecord $reserved): ProviderInvocationOutcome
    {
        $dispatching = $reserved->dispatching();
        if (! $this->persistence->transition($dispatching, $awaiting, $reserved->status, $reserved->version)) {
            return $this->currentOutcome($reserved->request);
        }

        try {
            $dispatch = $this->provider->dispatch($reserved->request);
        } catch (Throwable $error) {
            $detail = trim($error->getMessage());
            $dispatch = ProviderDispatchResult::acknowledgementUncertain($detail === '' ? 'Provider dispatch did not return a reliable acknowledgement.' : 'Provider dispatch did not return a reliable acknowledgement: '.$detail);
        }

        $record = $dispatching->record($dispatch);
        if ($dispatch->status === ProviderInvocationStatus::Accepted) {
            $owner = $this->persistence->findRunByProviderExecutionId($dispatch->acknowledgement->providerExecutionId)?->id;
            $binding = $this->binder->acknowledge($awaiting, $dispatch->acknowledgement, $owner);
            if ($binding->outcome === ProviderBindingOutcome::Conflict) {
                $record = $dispatching->record(ProviderDispatchResult::bindingConflict($dispatch->acknowledgement, $binding->failure->message));
                if (! $this->persistence->transition($record, $binding->run, $dispatching->status, $dispatching->version)) {
                    return $this->currentOutcome($reserved->request);
                }

                return new ProviderInvocationOutcome(ProviderInvocationStatus::BindingConflict, $binding->run, $record);
            }
            if (! $this->persistence->transition($record, $binding->run, $dispatching->status, $dispatching->version)) {
                return $this->currentOutcome($reserved->request);
            }

            return new ProviderInvocationOutcome(ProviderInvocationStatus::Accepted, $binding->run, $record);
        }

        $nextRun = $dispatch->status === ProviderInvocationStatus::AcknowledgementUncertain
            ? $awaiting->acknowledgementUncertain((string) $dispatch->reason)
            : $awaiting->dispatchFailed((string) $dispatch->reason);
        if (! $this->persistence->transition($record, $nextRun, $dispatching->status, $dispatching->version)) {
            return $this->currentOutcome($reserved->request);
        }

        return new ProviderInvocationOutcome($dispatch->status, $nextRun, $record);
    }

    private function replay(Run $run, ProviderInvocationRequest $request, ProviderInvocationRecord $record): ProviderInvocationOutcome
    {
        if ($record->request != $request) {
            throw new InvalidArgumentException('An invocation identity or idempotency key cannot be reused for different work.');
        }

        $persistedRun = $this->persistence->findRun($run->id) ?? $run;
        if ($record->status === ProviderInvocationStatus::Reserved) {
            return $this->invoke($persistedRun, $record);
        }
        if ($record->status === ProviderInvocationStatus::Dispatching) {
            $reason = 'Provider dispatch may have crossed the remote boundary before local completion was recorded.';
            $uncertain = $persistedRun->providerBindingStatus === ProviderBindingStatus::AwaitingAcknowledgement ? $persistedRun->acknowledgementUncertain($reason) : $persistedRun;
            $record = $record->record(ProviderDispatchResult::acknowledgementUncertain($reason));
            if (! $this->persistence->transition($record, $uncertain, ProviderInvocationStatus::Dispatching, $record->version - 1)) {
                return $this->currentOutcome($request);
            }

            return new ProviderInvocationOutcome(ProviderInvocationStatus::AcknowledgementUncertain, $uncertain, $record);
        }

        return new ProviderInvocationOutcome(ProviderInvocationStatus::Duplicate, $persistedRun, $record);
    }

    private function currentOutcome(ProviderInvocationRequest $request): ProviderInvocationOutcome
    {
        $record = $this->persistence->findInvocation($request->invocationId)
            ?? throw new InvalidArgumentException('The winning invocation transition is not readable.');
        $run = $this->persistence->findRun($request->runId)
            ?? throw new InvalidArgumentException('The winning Run transition is not readable.');

        return new ProviderInvocationOutcome($record->status, $run, $record);
    }

    private function assertMatchesProvenance(Run $run, ProviderInvocationRequest $request): void
    {
        if ($run->id->value !== $request->runId->value
            || $run->provenance->requestId->value !== $request->requestId->value
            || $run->provenance->taskId->value !== $request->taskId->value
            || $run->provenance->targetSelection->target->id->value !== $request->targetId->value
            || $run->provenance->targetSelection->target->provider !== $request->provider
            || $run->provenance->targetSelection->requirements->agentAdapter !== $request->agentAdapter
            || $run->provenance->promptId->value !== $request->prompt->id->value
            || $run->provenance->promptVersion !== $request->prompt->version
            || $run->provenance->promptCompilerVersion !== $request->prompt->compilerVersion
            || $run->provenance->promptProvenanceHash !== $request->prompt->provenanceHash
            || $run->provenance->executionIdentity?->canonicalIdentity() !== $request->executionIdentity?->canonicalIdentity()) {
            throw new InvalidArgumentException('Provider invocation must match immutable Run provenance.');
        }
    }
}
