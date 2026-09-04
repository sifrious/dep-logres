<?php

declare(strict_types=1);

namespace Sifrious\Logres\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sifrious\Logres\PostflightReport;
use Sifrious\Logres\PostflightResultAssembler;
use Sifrious\Logres\RequiredVerificationOutcome;
use Sifrious\Logres\RunResult;
use Sifrious\Logres\VerificationOutcomeAggregator;
use Sifrious\Logres\VerificationStatus;
use Sifrious\Logres\Tests\Fixtures\VerificationOutcomeFixtures;

final class VerificationOutcomeAggregatorTest extends TestCase
{
    #[Test]
    public function provider_claims_success_but_required_test_fails(): void
    {
        $outcome = (new VerificationOutcomeAggregator)->aggregate(
            VerificationOutcomeFixtures::providerClaimsSuccessRequiredTestFailsPlan(),
            VerificationOutcomeFixtures::providerClaimsSuccessRequiredTestFailsLog(),
        );

        self::assertSame(RequiredVerificationOutcome::Failed, $outcome->requiredVerification);
        self::assertSame(VerificationStatus::Failed, $outcome->verificationStatus);
        self::assertFalse($outcome->isVerifiedSuccess());
        self::assertSame('test_execution', $outcome->evidence[0]->kind);
        self::assertSame('Critical acceptance test', $outcome->checks[0]->checkName);
    }

    #[Test]
    public function no_change_expected_is_verified_when_required_command_passes(): void
    {
        $outcome = (new VerificationOutcomeAggregator)->aggregate(
            VerificationOutcomeFixtures::noChangeExpectedPlan(),
            VerificationOutcomeFixtures::noChangeExpectedLog(),
        );

        self::assertSame(RequiredVerificationOutcome::Passed, $outcome->requiredVerification);
        self::assertSame(VerificationStatus::Succeeded, $outcome->verificationStatus);
        self::assertTrue($outcome->isVerifiedSuccess());
        self::assertSame('command_execution', $outcome->evidence[0]->kind);
        self::assertSame(0, $outcome->checks[0]->exitStatus);
    }

    #[Test]
    public function commit_produced_with_failed_verification_is_not_verified_success(): void
    {
        $outcome = (new VerificationOutcomeAggregator)->aggregate(
            VerificationOutcomeFixtures::commitProducedVerificationFailedPlan(),
            VerificationOutcomeFixtures::commitProducedVerificationFailedLog(),
        );

        self::assertSame(2, count($outcome->checks));
        self::assertSame(RequiredVerificationOutcome::Failed, $outcome->requiredVerification);
        self::assertSame(VerificationStatus::Failed, $outcome->verificationStatus);
        self::assertFalse($outcome->isVerifiedSuccess());
    }

    #[Test]
    public function unavailable_required_check_cannot_be_verified_success(): void
    {
        $outcome = (new VerificationOutcomeAggregator)->aggregate(
            VerificationOutcomeFixtures::unavailableCheckPlan(),
            VerificationOutcomeFixtures::unavailableCheckLog(),
        );

        self::assertSame(RequiredVerificationOutcome::Unavailable, $outcome->requiredVerification);
        self::assertSame(VerificationStatus::Unavailable, $outcome->verificationStatus);
        self::assertFalse($outcome->isVerifiedSuccess());
        self::assertSame('tool_invocation', $outcome->evidence[0]->kind);
    }

    #[Test]
    public function provider_completion_claim_stays_separate_from_verified_outcome(): void
    {
        $provider = RunResult::succeeded(agentClaim: 'Task completed successfully.');
        $verified = (new VerificationOutcomeAggregator)->aggregate(
            VerificationOutcomeFixtures::providerClaimsSuccessRequiredTestFailsPlan(),
            VerificationOutcomeFixtures::providerClaimsSuccessRequiredTestFailsLog(),
        );
        $result = (new PostflightResultAssembler)->assemble(
            $provider,
            new PostflightReport(
                evidence: $verified->toRunEvidence(),
                observedOutcome: $verified->observedOutcome,
                completedAt: '2026-09-02T10:00:00Z',
                verificationStatus: $verified->verificationStatus,
            ),
        )->withRequiredVerification($verified->requiredVerification);

        self::assertSame('Task completed successfully.', $result->agentClaim);
        self::assertSame($verified->observedOutcome, $result->observedOutcome);
        self::assertSame(VerificationStatus::Failed, $result->verificationStatus);
        self::assertSame(RequiredVerificationOutcome::Failed, $result->requiredVerification);
        self::assertFalse($result->isVerifiedSuccess());
    }
}
