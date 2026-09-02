<?php

declare(strict_types=1);

namespace Sifrious\Logres\Tests;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Sifrious\Logres\AbstractHarness;
use Sifrious\Logres\AfterTurnHandler;
use Sifrious\Logres\AfterTurnPipeline;
use Sifrious\Logres\ArtifactReference;
use Sifrious\Logres\BeforeTurnHandler;
use Sifrious\Logres\BeforeTurnPipeline;
use Sifrious\Logres\EnvironmentSnapshot;
use Sifrious\Logres\ExecutionObserver;
use Sifrious\Logres\FinalizationStatus;
use Sifrious\Logres\HarnessCapability;
use Sifrious\Logres\HarnessHandle;
use Sifrious\Logres\HarnessProbe;
use Sifrious\Logres\HarnessStatus;
use Sifrious\Logres\PostflightReport;
use Sifrious\Logres\PostflightResultAssembler;
use Sifrious\Logres\RunEvidence;
use Sifrious\Logres\RunRequest;
use Sifrious\Logres\RunResult;
use Sifrious\Logres\RunResultHistorian;
use Sifrious\Logres\RunResultStore;
use Sifrious\Logres\RunStatus;
use Sifrious\Logres\Turn;
use Sifrious\Logres\TurnContext;
use Sifrious\Logres\TurnRunner;
use Sifrious\Logres\VerificationStatus;
use Sifrious\Logres\Tests\Fixtures\InvariantPipelines;

final class LifecycleFinalizationTest extends TestCase
{
    #[Test]
    public function preflight_rejection_never_invokes_provider(): void
    {
        $harness = new LifecycleHarness;
        $runner = self::runner(new BeforeTurnPipeline([new RejectingPreflight]), new AfterTurnPipeline);

        try {
        $runner->run(self::request(), self::context(), $harness, new LifecycleNullObserver);
            self::fail('Preflight should reject.');
        } catch (RuntimeException $failure) {
            self::assertSame('not authorized', $failure->getMessage());
        }

        self::assertSame(0, $harness->providerCalls);
    }

    #[Test]
    public function provider_success_verification_failure_is_not_verified(): void
    {
        $provider = RunResult::succeeded(agentClaim: 'all tests pass');
        $report = new PostflightReport(
            [new RunEvidence('verification', 'test-run:failed', '2026-08-29T12:00:00Z')],
            'Independent tests failed.',
            '2026-08-29T12:00:00Z',
            VerificationStatus::Failed,
        );

        $result = (new PostflightResultAssembler)->assemble($provider, $report);

        self::assertSame(RunStatus::Succeeded, $result->status, 'Runtime disposition remains completed.');
        self::assertSame(VerificationStatus::Failed, $result->verificationStatus);
        self::assertFalse($result->isVerifiedSuccess());
    }

    #[Test]
    public function provider_success_verification_success_is_verified(): void
    {
        $result = (new PostflightResultAssembler)->assemble(
            RunResult::succeeded(),
            new PostflightReport([], 'Acceptance criteria satisfied.', '2026-08-29T12:00:00Z', VerificationStatus::Succeeded),
        );

        self::assertTrue($result->isVerifiedSuccess());
    }

    #[Test]
    public function multiple_commits_preserve_order_and_range(): void
    {
        $result = (new PostflightResultAssembler)->assemble(RunResult::failed('provider failed'), new PostflightReport([
            new RunEvidence('git.commit', 'sha:first', '2026-08-29T12:00:00Z'),
            new RunEvidence('git.commit', 'sha:second', '2026-08-29T12:01:00Z'),
            new RunEvidence('git.range', 'first..second', '2026-08-29T12:01:00Z'),
        ], 'Two commits survived provider failure.', '2026-08-29T12:01:00Z', VerificationStatus::Failed));

        self::assertSame(['sha:first', 'sha:second', 'first..second'], array_column($result->evidence, 'reference'));
        self::assertSame(RunStatus::Failed, $result->status);
    }

    #[Test]
    public function provider_failure_preserves_git_evidence(): void
    {
        $result = (new PostflightResultAssembler)->assemble(
            RunResult::failed('provider reported failure', 1),
            new PostflightReport(
                [new RunEvidence('git.diff', 'comparison:starting-head..working-tree', '2026-08-29T12:00:00Z')],
                'Provider failed after changing tracked files.',
                '2026-08-29T12:00:00Z',
                VerificationStatus::Failed,
            ),
        );

        self::assertSame(RunStatus::Failed, $result->status);
        self::assertSame('comparison:starting-head..working-tree', $result->evidence[0]->reference);
    }

    #[Test]
    public function dirty_uncommitted_result_is_preserved(): void
    {
        $result = (new PostflightResultAssembler)->assemble(
            RunResult::succeeded(),
            new PostflightReport(
                [new RunEvidence('git.working-tree', 'dirty:no-commit', '2026-08-29T12:00:00Z')],
                'Dirty working tree with no produced commit.',
                '2026-08-29T12:00:00Z',
                VerificationStatus::Incomplete,
            ),
        );

        self::assertSame('dirty:no-commit', $result->evidence[0]->reference);
        self::assertFalse($result->isVerifiedSuccess());
    }

    #[Test]
    public function provider_crash_preserves_commit_evidence(): void
    {
        $harness = new LifecycleHarness(throwOnStart: true);
        $result = (self::runner(
            new BeforeTurnPipeline,
            new AfterTurnPipeline([new RecordingPostflight]),
        ))->run(self::request(), self::context(), $harness, new LifecycleNullObserver);

        self::assertSame(RunStatus::ProviderError, $result->status);
        self::assertSame('sha:partial', $result->evidence[0]->reference);
    }

    #[Test]
    public function clean_tree_with_unmet_acceptance_criteria_is_not_verified_success(): void
    {
        $result = (new PostflightResultAssembler)->assemble(
            RunResult::succeeded(agentClaim: 'done'),
            new PostflightReport(
                [new RunEvidence('git.working-tree', 'clean:no-observed-change', '2026-08-29T12:00:00Z')],
                'Acceptance criteria were not satisfied.',
                '2026-08-29T12:00:00Z',
                VerificationStatus::Failed,
            ),
        );

        self::assertSame(RunStatus::Succeeded, $result->status);
        self::assertSame(VerificationStatus::Failed, $result->verificationStatus);
        self::assertFalse($result->isVerifiedSuccess());
    }

    #[Test]
    public function timeout_still_runs_finalization(): void
    {
        $result = (new AfterTurnPipeline([new RecordingPostflight]))->process(
            self::request(), self::context(), RunResult::timedOut(reason: 'deadline'),
        );

        self::assertSame(RunStatus::TimedOut, $result->status);
        self::assertSame(FinalizationStatus::Complete, $result->finalizationStatus);
    }

    #[Test]
    public function cancellation_still_runs_finalization(): void
    {
        $result = (new AfterTurnPipeline([new RecordingPostflight]))->process(
            self::request(), self::context(), RunResult::cancelled(reason: 'operator'),
        );

        self::assertSame(RunStatus::Cancelled, $result->status);
        self::assertSame(FinalizationStatus::Complete, $result->finalizationStatus);
    }

    #[Test]
    public function provider_exception_still_runs_finalization_and_preserves_evidence(): void
    {
        $harness = new LifecycleHarness(throwOnStart: true);
        $after = new RecordingPostflight;
        $runner = self::runner(new BeforeTurnPipeline, new AfterTurnPipeline([$after]));

        $result = $runner->run(self::request(), self::context(), $harness, new LifecycleNullObserver);

        self::assertSame(1, $after->calls);
        self::assertSame(RunStatus::ProviderError, $result->status);
        self::assertSame('sha:partial', $result->evidence[0]->reference);
        self::assertSame(FinalizationStatus::Complete, $result->finalizationStatus);
    }

    #[Test]
    public function postflight_unavailable_is_incomplete_not_success(): void
    {
        $runner = self::runner(new BeforeTurnPipeline, new AfterTurnPipeline([new FailingPostflight]));
        $result = $runner->run(self::request(), self::context(), new LifecycleHarness, new LifecycleNullObserver);

        self::assertSame(FinalizationStatus::Incomplete, $result->finalizationStatus);
        self::assertSame(VerificationStatus::Incomplete, $result->verificationStatus);
        self::assertFalse($result->isVerifiedSuccess());
    }

    #[Test]
    public function historian_failure_does_not_destroy_local_result_and_replay_does_not_repeat_provider(): void
    {
        $store = new MemoryResultStore;
        $harness = new LifecycleHarness;
        $runner = self::runner(
            new BeforeTurnPipeline,
            new AfterTurnPipeline([new RecordingPostflight(VerificationStatus::Succeeded)]),
            $store,
            new FailingHistorian,
        );
        $request = self::request('same-operation');

        $first = $runner->run($request, self::context(), $harness, new LifecycleNullObserver);
        $second = $runner->run($request, self::context(), $harness, new LifecycleNullObserver);

        self::assertTrue($first->isVerifiedSuccess());
        self::assertSame($first, $store->find($request->identity()));
        self::assertSame($first, $second);
        self::assertSame(1, $harness->providerCalls);
    }

    private static function request(?string $key = null): RunRequest
    {
        return new RunRequest(new Turn('execute'), 'fixture', 'workspace', $key);
    }

    private static function runner(
        BeforeTurnPipeline $before,
        AfterTurnPipeline $after,
        ?RunResultStore $results = null,
        ?RunResultHistorian $historian = null,
    ): TurnRunner {
        return new TurnRunner(
            InvariantPipelines::preflight(),
            $before,
            InvariantPipelines::finalization(),
            $after,
            $results,
            $historian,
        );
    }

    private static function context(): TurnContext
    {
        return new TurnContext(['actor' => 'fixture'], new EnvironmentSnapshot('test', '1', 'host', '/bin/test'));
    }
}

final class LifecycleHarness extends AbstractHarness
{
    public int $providerCalls = 0;

    public function __construct(private readonly bool $throwOnStart = false)
    {
        parent::__construct('fixture', new HarnessCapability('fixture', true, true, true, false));
    }

    public function probe(): HarnessProbe
    {
        return HarnessProbe::available(new EnvironmentSnapshot('test', '1', 'host', '/bin/test'));
    }

    protected function startHarness(RunRequest $request, TurnContext $context, ExecutionObserver $observer): HarnessHandle
    {
        $this->providerCalls++;
        if ($this->throwOnStart) {
            throw new RuntimeException('provider crashed');
        }

        return new HarnessHandle('attempt', 'fixture', new DateTimeImmutable('2026-08-29T12:00:00Z'));
    }

    public function status(HarnessHandle $handle, ExecutionObserver $observer): HarnessStatus
    {
        return HarnessStatus::terminal(RunResult::succeeded());
    }

    public function cancel(HarnessHandle $handle): void {}
}

final class RejectingPreflight implements BeforeTurnHandler
{
    public function handle(RunRequest $request, TurnContext $context): TurnContext
    {
        throw new RuntimeException('not authorized');
    }
}

final class RecordingPostflight implements AfterTurnHandler
{
    public int $calls = 0;

    public function __construct(private readonly VerificationStatus $verification = VerificationStatus::Failed) {}

    public function handle(RunRequest $request, TurnContext $context, RunResult $result): RunResult
    {
        $this->calls++;

        return (new PostflightResultAssembler)->assemble($result, new PostflightReport(
            [new RunEvidence('git.commit', 'sha:partial', '2026-08-29T12:01:00Z')],
            'observed independently',
            '2026-08-29T12:01:00Z',
            $this->verification,
        ));
    }
}

final class FailingPostflight implements AfterTurnHandler
{
    public function handle(RunRequest $request, TurnContext $context, RunResult $result): RunResult
    {
        throw new RuntimeException('observer unavailable');
    }
}

final class MemoryResultStore implements RunResultStore
{
    /** @var array<string, RunResult> */
    private array $results = [];

    public function find(string $requestIdentity): ?RunResult
    {
        return $this->results[$requestIdentity] ?? null;
    }

    public function save(string $requestIdentity, RunResult $result): void
    {
        $this->results[$requestIdentity] = $result;
    }
}

final class FailingHistorian implements RunResultHistorian
{
    public function export(string $requestIdentity, RunResult $result): void
    {
        throw new RuntimeException('historian unavailable');
    }
}

final class LifecycleNullObserver implements ExecutionObserver
{
    public function contextResolved(TurnContext $context): void {}
    public function processStarted(HarnessHandle $handle): void {}
    public function stdout(string $chunk): void {}
    public function stderr(string $chunk): void {}
    public function artifact(ArtifactReference $artifact): void {}
}
