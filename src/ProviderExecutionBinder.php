<?php

declare(strict_types=1);

namespace Sifrious\Logres;

final class ProviderExecutionBinder
{
    public function acknowledge(
        Run $run,
        ProviderAcknowledgement $acknowledgement,
        ?RunId $existingOwner = null,
    ): ProviderBindingResult {
        if ($run->providerBindingStatus === ProviderBindingStatus::ValidationBlocked) {
            return $this->rejectWithoutTransition($run, 'validation_blocked', 'A validation-blocked Run cannot accept a provider acknowledgement.');
        }

        $target = $run->provenance->targetSelection->target;

        if ($acknowledgement->providerExecutionId->provider !== $target->provider
            || $acknowledgement->targetId->value !== $target->id->value) {
            return $this->conflict($run, 'provider_acknowledgement_mismatch', 'The acknowledgement does not identify the selected provider and target.');
        }

        if ($existingOwner !== null && $existingOwner->value !== $run->id->value) {
            return $this->conflict($run, 'provider_execution_already_bound', 'The provider execution identity belongs to another Run.');
        }

        if ($run->providerExecutionId !== null) {
            if ($run->providerExecutionId->canonical() === $acknowledgement->providerExecutionId->canonical()) {
                return new ProviderBindingResult(ProviderBindingOutcome::Duplicate, $run);
            }

            return $this->conflict($run, 'conflicting_provider_execution', 'The Run is already bound to a different provider execution identity.');
        }

        if ($run->providerBindingStatus === ProviderBindingStatus::NotDispatched) {
            return $this->conflict($run, 'acknowledgement_before_dispatch', 'A provider acknowledgement cannot bind before dispatch begins.');
        }

        if ($run->providerBindingStatus === ProviderBindingStatus::ConflictingAcknowledgement) {
            return $this->conflict($run, 'unresolved_provider_conflict', 'The existing provider identity conflict requires operator reconciliation.');
        }

        return new ProviderBindingResult(
            ProviderBindingOutcome::Acknowledged,
            $run->acknowledged($acknowledgement->providerExecutionId, $acknowledgement->receivedAt),
        );
    }

    public function reconcile(
        Run $run,
        ProviderExecutionLookupResult $lookup,
        ?RunId $existingOwner = null,
    ): ProviderBindingResult {
        if ($run->providerBindingStatus === ProviderBindingStatus::ValidationBlocked) {
            return $this->rejectWithoutTransition($run, 'validation_blocked', 'A validation-blocked Run cannot be reconciled with a provider execution.');
        }

        if ($run->providerBindingStatus === ProviderBindingStatus::NotDispatched) {
            return $this->conflict($run, 'reconciliation_before_dispatch', 'A provider execution cannot be reconciled before dispatch begins.');
        }

        if ($lookup->status === ProviderLookupStatus::Found) {
            if ($lookup->acknowledgement === null) {
                return $this->conflict($run, 'invalid_provider_lookup', 'A found provider lookup requires an acknowledgement.');
            }

            return $this->acknowledge($run, $lookup->acknowledgement, $existingOwner);
        }

        $failure = new ProviderBindingFailure(
            $lookup->status === ProviderLookupStatus::NotFound ? 'provider_execution_not_found' : 'provider_lookup_unavailable',
            $lookup->reason,
        );

        return new ProviderBindingResult(
            ProviderBindingOutcome::ReconciliationRequired,
            $run->reconciliationRequired($failure->message),
            $failure,
        );
    }

    private function conflict(Run $run, string $code, string $message): ProviderBindingResult
    {
        $failure = new ProviderBindingFailure($code, $message);

        return new ProviderBindingResult(
            ProviderBindingOutcome::Conflict,
            $run->conflictingAcknowledgement($message),
            $failure,
        );
    }

    private function rejectWithoutTransition(Run $run, string $code, string $message): ProviderBindingResult
    {
        return new ProviderBindingResult(
            ProviderBindingOutcome::Conflict,
            $run,
            new ProviderBindingFailure($code, $message),
        );
    }
}
