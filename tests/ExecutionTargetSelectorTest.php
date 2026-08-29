<?php

declare(strict_types=1);

namespace Sifrious\Logres\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sifrious\Logres\ExecutionTargetId;
use Sifrious\Logres\ExecutionTargetCatalogReadModel;
use Sifrious\Logres\ExecutionTargetReadModel;
use Sifrious\Logres\ExecutionTargetSelector;
use Sifrious\Logres\TargetAvailability;
use Sifrious\Logres\TargetHealth;
use Sifrious\Logres\TargetSelectionReason;
use Sifrious\Logres\Tests\Fixtures\ExecutionTargetFixtures;
use Sifrious\Logres\Tests\Fixtures\InMemoryExecutionTargetStore;
use Sifrious\Logres\Tests\Fixtures\StaticExecutionTargetCatalog;

final class ExecutionTargetSelectorTest extends TestCase
{
    private const SELECTED_AT = '2026-08-28T04:31:00Z';

    #[Test]
    public function one_eligible_authorized_target_is_selected_automatically(): void
    {
        $requirements = ExecutionTargetFixtures::requirements();
        $catalog = new StaticExecutionTargetCatalog([ExecutionTargetFixtures::candidate()]);
        $result = (new ExecutionTargetSelector)->select(
            $requirements,
            $catalog->discover($requirements),
            ExecutionTargetFixtures::authorization(),
            self::SELECTED_AT,
        );

        self::assertTrue($result->selectedSuccessfully());
        self::assertSame('target:orbs:orb-a', $result->selection?->target->id->value);
        self::assertSame(TargetSelectionReason::Automatic, $result->selection?->reason);
        self::assertSame([], $result->selection?->alternateTargetIds);
    }

    #[Test]
    public function incapable_target_is_rejected(): void
    {
        $result = (new ExecutionTargetSelector)->select(
            ExecutionTargetFixtures::requirements(['git', 'php', 'browser']),
            [ExecutionTargetFixtures::candidate()],
            ExecutionTargetFixtures::authorization(),
            self::SELECTED_AT,
        );

        self::assertSame('NO_ELIGIBLE_TARGET', $result->failures[0]->code);
        self::assertSame('TARGET_CAPABILITY_MISMATCH', $result->failures[0]->candidateEvaluations[0]->rejectionReasons[0]['code']);
    }

    #[Test]
    public function absent_provider_inventory_is_unavailable_not_incapable(): void
    {
        $result = (new ExecutionTargetSelector)->select(
            ExecutionTargetFixtures::requirements(),
            [],
            ExecutionTargetFixtures::authorization(),
            self::SELECTED_AT,
        );

        self::assertSame('NO_TARGETS_DISCOVERED', $result->failures[0]->code);
    }

    #[Test]
    public function unavailable_or_unhealthy_target_is_rejected(): void
    {
        $selector = new ExecutionTargetSelector;
        $requirements = ExecutionTargetFixtures::requirements();

        $busy = $selector->select(
            $requirements,
            [ExecutionTargetFixtures::candidate(availability: TargetAvailability::Busy)],
            ExecutionTargetFixtures::authorization(),
            self::SELECTED_AT,
        );
        $degraded = $selector->select(
            $requirements,
            [ExecutionTargetFixtures::candidate(health: TargetHealth::Degraded)],
            ExecutionTargetFixtures::authorization(),
            self::SELECTED_AT,
        );

        self::assertSame('NO_ELIGIBLE_TARGET', $busy->failures[0]->code);
        self::assertSame('NO_ELIGIBLE_TARGET', $degraded->failures[0]->code);
    }

    #[Test]
    public function unauthorized_target_is_rejected_without_ambient_authority(): void
    {
        $result = (new ExecutionTargetSelector)->select(
            ExecutionTargetFixtures::requirements(),
            [ExecutionTargetFixtures::candidate()],
            ExecutionTargetFixtures::authorization([]),
            self::SELECTED_AT,
        );

        self::assertSame('NO_ELIGIBLE_TARGET', $result->failures[0]->code);
    }

    #[Test]
    public function multiple_equally_eligible_targets_use_stable_identity_tie_break(): void
    {
        $result = (new ExecutionTargetSelector)->select(
            ExecutionTargetFixtures::requirements(),
            [ExecutionTargetFixtures::candidate(), ExecutionTargetFixtures::candidate('orb-b')],
            ExecutionTargetFixtures::authorization(['target:orbs:orb-a', 'target:orbs:orb-b']),
            self::SELECTED_AT,
        );

        self::assertSame('target:orbs:orb-a', $result->selection?->target->id->value);
        self::assertNotNull($result->selection?->tieBreakReason);
    }

    #[Test]
    public function authorized_manual_override_selects_one_target_and_preserves_alternates(): void
    {
        $result = (new ExecutionTargetSelector)->select(
            ExecutionTargetFixtures::requirements(),
            [ExecutionTargetFixtures::candidate(), ExecutionTargetFixtures::candidate('orb-b')],
            ExecutionTargetFixtures::authorization(['target:orbs:orb-a', 'target:orbs:orb-b']),
            self::SELECTED_AT,
            new ExecutionTargetId('target:orbs:orb-b'),
        );

        self::assertTrue($result->selectedSuccessfully());
        self::assertSame('target:orbs:orb-b', $result->selection?->target->id->value);
        self::assertSame(TargetSelectionReason::ManualOverride, $result->selection?->reason);
        self::assertSame(['target:orbs:orb-a'], $result->selection?->alternateTargetIds);
    }

    #[Test]
    public function unauthorized_manual_override_uses_the_same_policy(): void
    {
        $result = (new ExecutionTargetSelector)->select(
            ExecutionTargetFixtures::requirements(),
            [ExecutionTargetFixtures::candidate(), ExecutionTargetFixtures::candidate('orb-b')],
            ExecutionTargetFixtures::authorization(['target:orbs:orb-a']),
            self::SELECTED_AT,
            new ExecutionTargetId('target:orbs:orb-b'),
        );

        self::assertSame('TARGET_OVERRIDE_REJECTED', $result->failures[0]->code);
    }

    #[Test]
    public function selection_store_and_read_model_preserve_auditable_snapshot(): void
    {
        $result = (new ExecutionTargetSelector)->select(
            ExecutionTargetFixtures::requirements(),
            [ExecutionTargetFixtures::candidate()],
            ExecutionTargetFixtures::authorization(),
            self::SELECTED_AT,
        );
        self::assertNotNull($result->selection);
        $store = new InMemoryExecutionTargetStore;
        $store->save($result->selection);
        $persisted = $store->findForTask($result->selection->taskId);
        self::assertNotNull($persisted);
        $model = ExecutionTargetReadModel::fromSelection($persisted);

        self::assertSame('orbs', $model->provider);
        self::assertSame('debian-12:a1.small', $model->runtime);
        self::assertSame(['git', 'php'], $model->requiredCapabilities);
        self::assertSame('automatic', $model->selectionReason);
        self::assertSame(self::SELECTED_AT, $model->selectedAt);
        self::assertSame('2026-08-28T04:30:00Z', $model->observedAt);
    }

    #[Test]
    public function catalog_read_model_preserves_provider_reported_states(): void
    {
        $model = ExecutionTargetCatalogReadModel::fromCandidates([
            ExecutionTargetFixtures::candidate(),
            ExecutionTargetFixtures::candidate('orb-b', TargetAvailability::Busy, currentTaskId: new \Sifrious\Logres\TaskId('task:accepted-fixture:define')),
            ExecutionTargetFixtures::candidate('orb-c', TargetAvailability::Offline, TargetHealth::Unhealthy),
            ExecutionTargetFixtures::candidate('orb-d', TargetAvailability::Degraded, TargetHealth::Degraded),
        ]);

        self::assertSame(['available', 'busy', 'offline', 'degraded'], array_column($model->targets, 'availability'));
        self::assertSame('task:accepted-fixture:define', $model->targets[1]['current_task_id']);
    }

    #[Test]
    public function fixed_inputs_are_independent_of_candidate_order(): void
    {
        $selector = new ExecutionTargetSelector;
        $requirements = ExecutionTargetFixtures::requirements();
        $authorization = ExecutionTargetFixtures::authorization(['target:orbs:orb-a', 'target:orbs:orb-b']);
        $a = ExecutionTargetFixtures::candidate('orb-a');
        $b = ExecutionTargetFixtures::candidate('orb-b');

        $first = $selector->select($requirements, [$b, $a], $authorization, self::SELECTED_AT);
        $second = $selector->select($requirements, [$a, $b], $authorization, self::SELECTED_AT);

        self::assertSame($first->selection?->target->id->value, $second->selection?->target->id->value);
        self::assertSame(
            array_map(static fn ($evaluation) => [$evaluation->candidate->id->value, $evaluation->rankKey], $first->selection?->candidateEvaluations ?? []),
            array_map(static fn ($evaluation) => [$evaluation->candidate->id->value, $evaluation->rankKey], $second->selection?->candidateEvaluations ?? []),
        );
    }

    #[Test]
    public function stale_snapshot_resource_exhaustion_and_forbidden_class_are_explicit(): void
    {
        $requirements = new \Sifrious\Logres\ExecutionTargetRequirements(
            ExecutionTargetFixtures::requirements()->taskId, 'orbs', 'workspace:personal', 'repository:atlas', 'codex', ['git', 'php'], ['customer-owned'], 30,
        );
        $candidate = new \Sifrious\Logres\ExecutionTargetCandidate(
            new ExecutionTargetId('target:orbs:orb-a'), 'orbs', TargetAvailability::Available, TargetHealth::Healthy,
            'debian-12:a1.small', 'production', 'workspace:personal', 'repository:atlas', ['amp'], ['git', 'php'], null,
            '2026-08-28T04:29:00Z', executionClass: 'managed-cloud', availableSlots: 0,
        );
        $result = (new ExecutionTargetSelector)->select($requirements, [$candidate], ExecutionTargetFixtures::authorization(), self::SELECTED_AT);
        $codes = array_column($result->failures[0]->candidateEvaluations[0]->rejectionReasons, 'code');

        self::assertContains('TARGET_STALE', $codes);
        self::assertContains('TARGET_RESOURCE_EXHAUSTED', $codes);
        self::assertContains('TARGET_EXECUTION_CLASS_FORBIDDEN', $codes);
    }

    #[Test]
    public function preference_and_override_preserve_automatic_and_effective_targets(): void
    {
        $base = ExecutionTargetFixtures::requirements();
        $requirements = new \Sifrious\Logres\ExecutionTargetRequirements($base->taskId, 'orbs', 'workspace:personal', 'repository:atlas', 'codex', ['git', 'php'], preferredTargetId: 'target:orbs:orb-b');
        $result = (new ExecutionTargetSelector)->select(
            $requirements,
            [ExecutionTargetFixtures::candidate('orb-a'), ExecutionTargetFixtures::candidate('orb-b')],
            ExecutionTargetFixtures::authorization(['target:orbs:orb-a', 'target:orbs:orb-b']),
            self::SELECTED_AT,
            new ExecutionTargetId('target:orbs:orb-a'),
            'Use the local reserved runner.',
        );

        self::assertSame('target:orbs:orb-b', $result->selection?->automaticTarget->id->value);
        self::assertSame('target:orbs:orb-a', $result->selection?->target->id->value);
        self::assertSame('user:mary', $result->selection?->override['actor']);
        self::assertSame('Use the local reserved runner.', $result->selection?->override['reason']);
    }
}
