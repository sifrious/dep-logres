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

        self::assertSame('target_incapable', $result->failures[0]->code);
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

        self::assertSame('target_unavailable', $result->failures[0]->code);
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

        self::assertSame('target_unavailable', $busy->failures[0]->code);
        self::assertSame('target_unavailable', $degraded->failures[0]->code);
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

        self::assertSame('target_unauthorized', $result->failures[0]->code);
    }

    #[Test]
    public function multiple_equally_eligible_targets_are_ambiguous(): void
    {
        $result = (new ExecutionTargetSelector)->select(
            ExecutionTargetFixtures::requirements(),
            [ExecutionTargetFixtures::candidate(), ExecutionTargetFixtures::candidate('orb-b')],
            ExecutionTargetFixtures::authorization(['target:orbs:orb-a', 'target:orbs:orb-b']),
            self::SELECTED_AT,
        );

        self::assertSame('target_ambiguous', $result->failures[0]->code);
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

        self::assertSame('target_unauthorized', $result->failures[0]->code);
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
}
