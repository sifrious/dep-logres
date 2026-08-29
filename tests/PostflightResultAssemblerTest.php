<?php
declare(strict_types=1);
namespace Sifrious\Logres\Tests;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sifrious\Logres\PostflightReport;
use Sifrious\Logres\PostflightResultAssembler;
use Sifrious\Logres\RunEvidence;
use Sifrious\Logres\RunResult;
final class PostflightResultAssemblerTest extends TestCase
{
    #[Test]
    public function it_preserves_provider_claims_beside_independent_observations(): void
    {
        $provider = RunResult::succeeded(agentClaim: 'Tests pass.');
        $report = new PostflightReport([
            new RunEvidence('git.commit', 'sha:abc', '2026-08-29T12:01:00Z'),
            new RunEvidence('verification', 'test-run:failed', '2026-08-29T12:02:00Z'),
        ], 'A commit exists but independent verification failed.', '2026-08-29T12:02:00Z');
        $result = (new PostflightResultAssembler)->assemble($provider, $report);
        self::assertSame('Tests pass.', $result->agentClaim);
        self::assertSame('A commit exists but independent verification failed.', $result->observedOutcome);
        self::assertCount(2, $result->evidence);
    }
}
