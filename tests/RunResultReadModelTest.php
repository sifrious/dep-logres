<?php
declare(strict_types=1);
namespace Sifrious\Logres\Tests;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sifrious\Logres\RunEvidence;
use Sifrious\Logres\RunResult;
use Sifrious\Logres\RunResultReadModel;
final class RunResultReadModelTest extends TestCase
{
    #[Test]
    public function it_groups_observed_evidence_for_presentation_without_reinterpreting_it(): void
    {
        $result = RunResult::succeeded(
            evidence: [
                new RunEvidence('git.commit', 'sha:abc', '2026-08-29T12:00:00Z'),
                new RunEvidence('verification', 'tests:1', '2026-08-29T12:01:00Z'),
                new RunEvidence('git.commit', 'sha:def', '2026-08-29T12:02:00Z'),
            ],
            agentClaim: 'Done.',
            observedOutcome: 'Two commits verified.',
        );
        $read = RunResultReadModel::fromResult($result);
        self::assertSame('succeeded', $read->status);
        self::assertSame(['sha:abc', 'sha:def'], array_column($read->evidence['git.commit'], 'reference'));
        self::assertSame('tests:1', $read->evidence['verification'][0]['reference']);
    }
}
