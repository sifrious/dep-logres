<?php

declare(strict_types=1);

namespace Sifrious\Logres\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sifrious\Logres\ExecutionTargetId;
use Sifrious\Logres\ExecutionTargetCandidate;
use Sifrious\Logres\ExecutionTargetRequirements;
use Sifrious\Logres\ExecutionTargetCatalogReadModel;
use Sifrious\Logres\ExecutionTargetReadModel;
use Sifrious\Logres\ExecutionTargetSelector;
use Sifrious\Logres\TargetAvailability;
use Sifrious\Logres\TargetHealth;
use Sifrious\Logres\TargetSelectionReason;
use Sifrious\Logres\TargetSelectionStatus;
use Sifrious\Logres\TaskId;
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

    #[Test]
    public function execution_class_policy_prevents_unauthorized_fallback(): void
    {
        $selector = new ExecutionTargetSelector;
        $authorization = ExecutionTargetFixtures::authorization(['target:orbs:local', 'target:orbs:managed', 'target:orbs:customer']);
        $local = $this->placementCandidate('local', 'local');
        $managed = $this->placementCandidate('managed', 'managed-cloud');
        $customer = $this->placementCandidate('customer', 'customer-owned');

        $localOnly = $selector->select($this->placementRequirements(['local']), [$managed, $local], $authorization, self::SELECTED_AT);
        self::assertSame('target:orbs:local', $localOnly->selection?->target->id->value);

        $gitBacked = $selector->select($this->placementRequirements(['managed-cloud']), [$managed], $authorization, self::SELECTED_AT);
        self::assertSame('target:orbs:managed', $gitBacked->selection?->target->id->value);

        $customerOnly = $selector->select($this->placementRequirements(['customer-owned']), [$managed], $authorization, self::SELECTED_AT);
        self::assertSame(TargetSelectionStatus::NeedsTarget, $customerOnly->status);
        self::assertSame('TARGET_EXECUTION_CLASS_FORBIDDEN', $customerOnly->failures[0]->candidateEvaluations[0]->rejectionReasons[0]['code']);
        self::assertNotSame($customer->id->value, $customerOnly->selection?->target->id->value);
    }

    #[Test]
    public function duplicate_provider_identities_fail_closed(): void
    {
        $first = $this->placementCandidate('duplicate-a', 'local', providerTargetId: 'machine:same');
        $second = $this->placementCandidate('duplicate-b', 'local', providerTargetId: 'machine:same');
        $result = (new ExecutionTargetSelector)->select(
            $this->placementRequirements(['local']), [$first, $second],
            ExecutionTargetFixtures::authorization([$first->id->value, $second->id->value]), self::SELECTED_AT,
        );

        self::assertSame(TargetSelectionStatus::NeedsTarget, $result->status);
        foreach ($result->failures[0]->candidateEvaluations as $evaluation) {
            self::assertContains('TARGET_DUPLICATE_IDENTITY', array_column($evaluation->rejectionReasons, 'code'));
        }
    }

    #[Test]
    public function machine_selection_is_independent_of_later_wardrobe_adapter_choice(): void
    {
        $candidate = $this->placementCandidate('neutral', 'local', agentAdapters: ['amp']);
        $requirements = new ExecutionTargetRequirements(
            new TaskId('task:neutral:1'), 'orbs', 'workspace:personal', 'repository:atlas', 'claude', ['git'], ['local'], 300,
        );
        $result = (new ExecutionTargetSelector)->select($requirements, [$candidate], ExecutionTargetFixtures::authorization([$candidate->id->value]), self::SELECTED_AT);

        self::assertTrue($result->selectedSuccessfully());
        self::assertSame('target:orbs:neutral', $result->selection?->target->id->value);
    }

    #[Test]
    public function selection_freezes_capability_version_inputs_and_candidate_reasons(): void
    {
        $candidate = $this->placementCandidate('snapshot', 'local');
        $result = (new ExecutionTargetSelector)->select($this->placementRequirements(['local']), [$candidate], ExecutionTargetFixtures::authorization([$candidate->id->value]), self::SELECTED_AT);
        self::assertNotNull($result->selection);
        $model = ExecutionTargetReadModel::fromSelection($result->selection);

        self::assertSame($candidate->capabilitySnapshot?->version, $model->capabilitySnapshotVersion);
        self::assertSame(ExecutionTargetSelector::POLICY_VERSION, $model->selectionPolicyVersion);
        self::assertSame($model->automaticTargetId, $model->effectiveTargetId);
        self::assertCount(1, $model->candidateEvaluations);
    }

    #[Test]
    public function ineligible_candidates_expose_stable_machine_reasons(): void
    {
        $cases = [
            'TARGET_STALE' => $this->placementCandidate('stale', 'local', observedAt: '2026-08-28T04:20:00Z'),
            'TARGET_CAPABILITY_MISMATCH' => $this->placementCandidate('incapable', 'local', capabilities: ['shell']),
            'TARGET_UNAVAILABLE' => $this->placementCandidate('offline', 'local', availability: TargetAvailability::Offline),
            'TARGET_UNHEALTHY' => $this->placementCandidate('unhealthy', 'local', health: TargetHealth::Unhealthy),
            'TARGET_RESOURCE_EXHAUSTED' => $this->placementCandidate('full', 'local', availableSlots: 0),
            'TARGET_WORKSPACE_MISMATCH' => $this->placementCandidate('wrong-workspace', 'local', workspaceAuthority: 'workspace:other'),
            'TARGET_EXECUTION_CLASS_FORBIDDEN' => $this->placementCandidate('managed-only', 'managed-cloud'),
        ];

        foreach ($cases as $reason => $candidate) {
            $result = (new ExecutionTargetSelector)->select(
                $this->placementRequirements(['local']), [$candidate],
                ExecutionTargetFixtures::authorization([$candidate->id->value]), self::SELECTED_AT,
            );
            self::assertContains($reason, array_column($result->failures[0]->candidateEvaluations[0]->rejectionReasons, 'code'), $reason);
        }

        $unauthorized = $this->placementCandidate('unauthorized', 'local');
        $result = (new ExecutionTargetSelector)->select($this->placementRequirements(['local']), [$unauthorized], ExecutionTargetFixtures::authorization([]), self::SELECTED_AT);
        self::assertContains('TARGET_UNAUTHORIZED', array_column($result->failures[0]->candidateEvaluations[0]->rejectionReasons, 'code'));
    }

    private function placementRequirements(array $classes): ExecutionTargetRequirements
    {
        return new ExecutionTargetRequirements(new TaskId('task:placement:1'), 'orbs', 'workspace:personal', 'repository:atlas', 'wardrobe-profile', ['git'], $classes, 300);
    }

    private function placementCandidate(
        string $id,
        string $executionClass,
        ?string $providerTargetId = null,
        array $agentAdapters = ['wardrobe-profile'],
        array $capabilities = ['git'],
        TargetAvailability $availability = TargetAvailability::Available,
        TargetHealth $health = TargetHealth::Healthy,
        int $availableSlots = 1,
        string $workspaceAuthority = 'workspace:personal',
        string $observedAt = '2026-08-28T04:30:00Z',
    ): ExecutionTargetCandidate
    {
        $snapshot = new \Sifrious\Logres\CapabilitySnapshot($capabilities, $agentAdapters, ['1'], new \DateTimeImmutable($observedAt));

        return new ExecutionTargetCandidate(
            new ExecutionTargetId("target:orbs:{$id}"), 'orbs', $availability, $health,
            'machine-runtime', 'production', $workspaceAuthority, 'repository:atlas', $agentAdapters, $capabilities, null,
            $observedAt, executionClass: $executionClass, executionNodeId: "runner:{$id}",
            providerTargetId: $providerTargetId ?? "machine:{$id}", capabilitySnapshotId: $snapshot->version,
            workspaceGrantId: 'grant:personal', workspaceIdentity: $workspaceAuthority, availableSlots: $availableSlots, capabilitySnapshot: $snapshot,
        );
    }
}
