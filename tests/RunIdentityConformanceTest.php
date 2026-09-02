<?php

declare(strict_types=1);

namespace Sifrious\Logres\Tests;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sifrious\Logres\ExecutionTargetId;
use Sifrious\Logres\ProviderAcknowledgement;
use Sifrious\Logres\ProviderBindingOutcome;
use Sifrious\Logres\ProviderBindingStatus;
use Sifrious\Logres\ProviderExecutionBinder;
use Sifrious\Logres\ProviderExecutionId;
use Sifrious\Logres\ProviderExecutionLookupResult;
use Sifrious\Logres\ProviderLookupStatus;
use Sifrious\Logres\RunId;
use Sifrious\Logres\RunIdentityConflict;
use Sifrious\Logres\RunIdentityReadModel;
use Sifrious\Logres\Tests\Fixtures\FakeProviderExecutionLookup;
use Sifrious\Logres\Tests\Fixtures\InMemoryRunStore;
use Sifrious\Logres\Tests\Fixtures\RunIdentityFixtures;

final class RunIdentityConformanceTest extends TestCase
{
    #[Test]
    public function provider_validation_failure_is_a_durable_blocked_state(): void
    {
        $original = RunIdentityFixtures::unauthorizedRun();
        $blocked = $original->validationBlocked(
            'PROVIDER_TARGET_UNKNOWN_OR_REVOKED',
            'The provider no longer recognizes the frozen target.',
            RunIdentityFixtures::DISPATCHED_AT,
        );

        self::assertSame(ProviderBindingStatus::ValidationBlocked, $blocked->providerBindingStatus);
        self::assertSame($original->provenance, $blocked->provenance);
        self::assertSame('PROVIDER_TARGET_UNKNOWN_OR_REVOKED', $blocked->preDispatchValidationFailure?->code);
        self::assertNull($blocked->providerExecutionId);
        self::assertNull($blocked->dispatchedAt);
        self::assertNull($blocked->acknowledgedAt);
        self::assertNull($blocked->dispatchAuthorization);
    }

    #[Test]
    public function blocked_run_cannot_dispatch_or_be_authorized(): void
    {
        $blocked = RunIdentityFixtures::unauthorizedRun()->validationBlocked(
            'TARGET_CHANGED',
            'Provider validation returned changed target facts.',
            RunIdentityFixtures::DISPATCHED_AT,
        );

        $this->expectException(InvalidArgumentException::class);
        $blocked->awaitingAcknowledgement(RunIdentityFixtures::DISPATCHED_AT);
    }

    #[Test]
    public function blocked_run_rejects_provider_binding_without_losing_failure_evidence(): void
    {
        $blocked = RunIdentityFixtures::unauthorizedRun()->validationBlocked(
            'TARGET_CHANGED',
            'Provider validation returned changed target facts.',
            RunIdentityFixtures::DISPATCHED_AT,
        );
        $binder = new ProviderExecutionBinder;

        $acknowledgement = $binder->acknowledge($blocked, RunIdentityFixtures::acknowledgement());
        $reconciliation = $binder->reconcile(
            $blocked,
            new ProviderExecutionLookupResult(ProviderLookupStatus::Found, RunIdentityFixtures::acknowledgement()),
        );

        self::assertSame(ProviderBindingOutcome::Conflict, $acknowledgement->outcome);
        self::assertSame('validation_blocked', $acknowledgement->failure?->code);
        self::assertSame($blocked, $acknowledgement->run);
        self::assertSame(ProviderBindingOutcome::Conflict, $reconciliation->outcome);
        self::assertSame('validation_blocked', $reconciliation->failure?->code);
        self::assertSame($blocked, $reconciliation->run);
    }

    #[Test]
    public function validation_failure_requires_a_complete_structured_shape(): void
    {
        $this->expectException(InvalidArgumentException::class);
        RunIdentityFixtures::unauthorizedRun()->validationBlocked('', '', 'not-a-timestamp');
    }

    #[Test]
    public function local_run_exists_with_immutable_provenance_before_dispatch(): void
    {
        $run = RunIdentityFixtures::run();
        $store = new InMemoryRunStore;
        $store->create($run);
        $persisted = $store->find($run->id);

        self::assertSame(ProviderBindingStatus::NotDispatched, $persisted?->providerBindingStatus);
        self::assertNull($persisted?->providerExecutionId);
        self::assertSame('prompt:task:inspect:v1', $persisted?->provenance->promptId->value);
        self::assertSame('target:orbs:orb-a', $persisted?->provenance->targetSelection->target->id->value);
        self::assertSame('repository:atlas', $persisted?->provenance->targetSelection->target->repositoryIdentity);
    }

    #[Test]
    public function first_acknowledgement_binds_and_duplicate_is_idempotent(): void
    {
        $binder = new ProviderExecutionBinder;
        $run = RunIdentityFixtures::run()->awaitingAcknowledgement(RunIdentityFixtures::DISPATCHED_AT);
        $acknowledgement = RunIdentityFixtures::acknowledgement();
        $first = $binder->acknowledge($run, $acknowledgement);
        $duplicate = $binder->acknowledge($first->run, $acknowledgement, $first->run->id);

        self::assertSame(ProviderBindingOutcome::Acknowledged, $first->outcome);
        self::assertSame('orbs:execution-001', $first->run->providerExecutionId?->canonical());
        self::assertSame(ProviderBindingOutcome::Duplicate, $duplicate->outcome);
        self::assertSame($first->run, $duplicate->run);
    }

    #[Test]
    public function delayed_acknowledgement_recovers_an_uncertain_run(): void
    {
        $run = RunIdentityFixtures::run()
            ->awaitingAcknowledgement(RunIdentityFixtures::DISPATCHED_AT)
            ->acknowledgementUncertain('The dispatch response was lost.');
        $result = (new ProviderExecutionBinder)->acknowledge($run, RunIdentityFixtures::acknowledgement());

        self::assertSame(ProviderBindingOutcome::Acknowledged, $result->outcome);
        self::assertSame(ProviderBindingStatus::Acknowledged, $result->run->providerBindingStatus);
    }

    #[Test]
    public function lost_acknowledgement_is_reconciled_through_provider_lookup(): void
    {
        $run = RunIdentityFixtures::run()
            ->awaitingAcknowledgement(RunIdentityFixtures::DISPATCHED_AT)
            ->acknowledgementUncertain('The dispatch response was lost.');
        $provider = new FakeProviderExecutionLookup(new ProviderExecutionLookupResult(
            ProviderLookupStatus::Found,
            RunIdentityFixtures::acknowledgement(),
        ));
        $result = (new ProviderExecutionBinder)->reconcile($run, $provider->find($run));

        self::assertSame(ProviderBindingOutcome::Acknowledged, $result->outcome);
        self::assertSame('orbs:execution-001', $result->run->providerExecutionId?->canonical());
    }

    #[Test]
    public function missing_provider_execution_remains_explicitly_reconcilable(): void
    {
        $run = RunIdentityFixtures::run()
            ->awaitingAcknowledgement(RunIdentityFixtures::DISPATCHED_AT)
            ->acknowledgementUncertain('The dispatch response was lost.');
        $lookup = new ProviderExecutionLookupResult(ProviderLookupStatus::NotFound, reason: 'No matching execution was found.');
        $result = (new ProviderExecutionBinder)->reconcile($run, $lookup);

        self::assertSame(ProviderBindingOutcome::ReconciliationRequired, $result->outcome);
        self::assertSame(ProviderBindingStatus::ReconciliationRequired, $result->run->providerBindingStatus);
        self::assertSame('provider_execution_not_found', $result->failure?->code);
    }

    #[Test]
    public function provider_identity_conflict_is_rejected_and_preserves_the_first_binding(): void
    {
        $binder = new ProviderExecutionBinder;
        $bound = $binder->acknowledge(
            RunIdentityFixtures::run()->awaitingAcknowledgement(RunIdentityFixtures::DISPATCHED_AT),
            RunIdentityFixtures::acknowledgement(),
        )->run;
        $conflict = $binder->acknowledge($bound, RunIdentityFixtures::acknowledgement('execution-002'));

        self::assertSame(ProviderBindingOutcome::Conflict, $conflict->outcome);
        self::assertSame(ProviderBindingStatus::ConflictingAcknowledgement, $conflict->run->providerBindingStatus);
        self::assertSame('orbs:execution-001', $conflict->run->providerExecutionId?->canonical());
        self::assertSame('conflicting_provider_execution', $conflict->failure?->code);
    }

    #[Test]
    public function provider_identity_cannot_move_between_runs(): void
    {
        $result = (new ProviderExecutionBinder)->acknowledge(
            RunIdentityFixtures::run('fixture-002')->awaitingAcknowledgement(RunIdentityFixtures::DISPATCHED_AT),
            RunIdentityFixtures::acknowledgement(),
            new RunId('run:fixture-001'),
        );

        self::assertSame(ProviderBindingOutcome::Conflict, $result->outcome);
        self::assertSame('provider_execution_already_bound', $result->failure?->code);
    }

    #[Test]
    public function persistence_adapter_enforces_provider_identity_uniqueness(): void
    {
        $binder = new ProviderExecutionBinder;
        $store = new InMemoryRunStore;
        $first = $binder->acknowledge(
            RunIdentityFixtures::run()->awaitingAcknowledgement(RunIdentityFixtures::DISPATCHED_AT),
            RunIdentityFixtures::acknowledgement(),
        )->run;
        $second = $binder->acknowledge(
            RunIdentityFixtures::run('fixture-002')->awaitingAcknowledgement(RunIdentityFixtures::DISPATCHED_AT),
            RunIdentityFixtures::acknowledgement(),
        )->run;
        $store->create($first);

        $this->expectException(RunIdentityConflict::class);
        $store->create($second);
    }

    #[Test]
    public function acknowledgement_must_match_the_selected_provider_and_target(): void
    {
        $acknowledgement = new ProviderAcknowledgement(
            new ProviderExecutionId('orbs', 'execution-001'),
            new ExecutionTargetId('target:orbs:orb-b'),
            RunIdentityFixtures::ACKNOWLEDGED_AT,
        );
        $result = (new ProviderExecutionBinder)->acknowledge(
            RunIdentityFixtures::run()->awaitingAcknowledgement(RunIdentityFixtures::DISPATCHED_AT),
            $acknowledgement,
        );

        self::assertSame('provider_acknowledgement_mismatch', $result->failure?->code);
    }

    #[Test]
    public function operator_read_model_discloses_identity_and_provenance_without_prompt_or_credentials(): void
    {
        $run = (new ProviderExecutionBinder)->acknowledge(
            RunIdentityFixtures::run()->awaitingAcknowledgement(RunIdentityFixtures::DISPATCHED_AT),
            RunIdentityFixtures::acknowledgement(),
        )->run;
        $model = RunIdentityReadModel::fromRun($run);
        $serialized = json_encode($model, JSON_THROW_ON_ERROR);

        self::assertSame('acknowledged', $model->providerBindingStatus);
        self::assertSame('debian-12:a1.small', $model->runtime);
        self::assertSame('orbs:execution-001', $model->providerExecutionId);
        self::assertStringNotContainsString('Task execution prompt', $serialized);
        self::assertStringNotContainsString('credential', $serialized);
    }
}
