<?php

declare(strict_types=1);

namespace Sifrious\Logres\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class PhaseDeliveryLoopMapTest extends TestCase
{
    private const REQUIRED_TRANSITION_FIELDS = [
        'id',
        'name',
        'owning_package',
        'consumed_contract',
        'produced_object_or_event',
        'persistence_owner',
        'authorization_check',
        'retry_or_idempotency_boundary',
        'evidence_emitted',
        'unresolved_gap',
        'source_paths',
        'test_paths',
    ];

    #[Test]
    public function every_transition_has_one_complete_audit_record(): void
    {
        $fixture = $this->fixture();
        $ids = [];

        self::assertGreaterThanOrEqual(20, count($fixture['transitions']));

        foreach ($fixture['transitions'] as $transition) {
            self::assertSame(
                self::REQUIRED_TRANSITION_FIELDS,
                array_keys($transition),
                "Transition {$transition['id']} changed the authoritative audit shape.",
            );
            self::assertNotContains($transition['id'], $ids, "Duplicate transition {$transition['id']}.");
            self::assertNotSame('', trim($transition['owning_package']));
            self::assertNotEmpty($transition['consumed_contract']);
            self::assertNotEmpty($transition['produced_object_or_event']);
            self::assertNotSame('', trim($transition['persistence_owner']));
            self::assertNotSame('', trim($transition['authorization_check']));
            self::assertNotSame('', trim($transition['retry_or_idempotency_boundary']));
            self::assertNotSame('', trim($transition['evidence_emitted']));
            self::assertTrue(
                $transition['source_paths'] !== [] || $transition['unresolved_gap'] !== null,
                "Transition {$transition['id']} must cite source or state the unresolved gap.",
            );
            $ids[] = $transition['id'];
        }
    }

    #[Test]
    public function every_packet_field_maps_to_an_owner_and_source_or_gap(): void
    {
        foreach ($this->fixture()['projections'] as $name => $projection) {
            self::assertNotSame('', trim($projection['decision']));
            self::assertNotSame('', trim($projection['persistence']));
            self::assertNotEmpty($projection['fields']);

            foreach ($projection['fields'] as $field) {
                self::assertNotSame('', trim($field['name']), "{$name} contains an unnamed field.");
                self::assertNotSame('', trim($field['owner']), "{$name}.{$field['name']} has no owner.");
                self::assertTrue(
                    $field['source_path'] !== null || $field['gap'] !== null,
                    "{$name}.{$field['name']} must map to existing source or a documented gap.",
                );
            }
        }
    }

    #[Test]
    public function audit_is_revision_pinned_and_keeps_mme_677_excluded(): void
    {
        $fixture = $this->fixture();

        self::assertSame(
            'Static source, tests, migrations, and documentation only; no provider calls.',
            $fixture['audit_scope'],
        );
        self::assertArrayHasKey('sifrious/dep-logres', $fixture['audited_revisions']);

        foreach ($fixture['audited_revisions'] as $repository => $revision) {
            self::assertMatchesRegularExpression('/^[a-f0-9]{40}$/', $revision, "{$repository} is not pinned.");
        }

        self::assertContains('MME-677', array_column($fixture['excluded'], 'id'));
    }

    /** @return array<string, mixed> */
    private function fixture(): array
    {
        return json_decode(
            file_get_contents(__DIR__.'/Fixtures/phase-delivery-loop.v1.json'),
            true,
            flags: JSON_THROW_ON_ERROR,
        );
    }
}
