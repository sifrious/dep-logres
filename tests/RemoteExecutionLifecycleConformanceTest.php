<?php

declare(strict_types=1);

namespace Sifrious\Logres\Tests;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sifrious\Logres\AttemptId;
use Sifrious\Logres\AbstractHarness;
use Sifrious\Logres\AfterTurnHandler;
use Sifrious\Logres\AfterTurnPipeline;
use Sifrious\Logres\ArtifactReference;
use Sifrious\Logres\BeforeTurnHandler;
use Sifrious\Logres\BeforeTurnPipeline;
use Sifrious\Logres\CancellationAuthorization;
use Sifrious\Logres\CancellationKind;
use Sifrious\Logres\ExecutionNodeRef;
use Sifrious\Logres\ExecutionObserver;
use Sifrious\Logres\ExecutionState;
use Sifrious\Logres\ExecutionStateReadModel;
use Sifrious\Logres\ExecutionStateRejected;
use Sifrious\Logres\ExecutionStateRejectionReason;
use Sifrious\Logres\FailureClassification;
use Sifrious\Logres\LeaseId;
use Sifrious\Logres\LeaseToken;
use Sifrious\Logres\EnvironmentSnapshot;
use Sifrious\Logres\HarnessCapability;
use Sifrious\Logres\HarnessHandle;
use Sifrious\Logres\HarnessProbe;
use Sifrious\Logres\HarnessStatus;
use Sifrious\Logres\PostflightReport;
use Sifrious\Logres\PostflightResultAssembler;
use Sifrious\Logres\RetryPolicy;
use Sifrious\Logres\RunId;
use Sifrious\Logres\RunResult;
use Sifrious\Logres\RunRequest;
use Sifrious\Logres\RunStatus;
use Sifrious\Logres\Tests\Fixtures\InMemoryExecutionStateStore;
use Sifrious\Logres\Tests\Fixtures\RunIdentityFixtures;
use Sifrious\Logres\Tests\Fixtures\InvariantPipelines;
use Sifrious\Logres\Turn;
use Sifrious\Logres\TurnContext;
use Sifrious\Logres\TurnRunner;
use Sifrious\Logres\VerificationStatus;

final class RemoteExecutionLifecycleConformanceTest extends TestCase
{
    #[Test]
    public function authorized_target_flows_through_lease_preflight_invocation_verification_and_terminal_state(): void
    {
        $authorizedRun = RunIdentityFixtures::run('conformance-authorized');
        self::assertNotNull($authorizedRun->dispatchAuthorization);
        $attemptId = new AttemptId('attempt:authorized:1');
        $token = new LeaseToken('token:authorized');
        $state = ExecutionState::create($authorizedRun->id, $this->at(0), RunIdentityFixtures::executionIdentity())
            ->scheduleAttempt($attemptId, $this->at(0))
            ->acquireLease($attemptId, new LeaseId('lease:authorized'), new ExecutionNodeRef('node:authorized'), $token, 'acquire:authorized', $this->at(1), 60)
            ->start($attemptId, $token, $this->at(2));
        $harness = new ConformanceHarness;
        $result = (new TurnRunner(
            InvariantPipelines::preflight(),
            new BeforeTurnPipeline([new AuthorizedRunPreflight($authorizedRun)]),
            InvariantPipelines::finalization(),
            new AfterTurnPipeline([new VerifiedConformancePostflight]),
        ))->run(
            new RunRequest(new Turn('execute authorized work'), 'conformance', 'workspace:personal', 'request:authorized'),
            new TurnContext(['actor' => 'user:mary'], new EnvironmentSnapshot('test', '1', 'host', '/bin/test')),
            $harness,
            new ConformanceObserver,
        );
        $terminal = $state->finish($attemptId, $token, RunStatus::Succeeded, $this->at(4), resultReference: 'result:verified');

        self::assertSame(1, $harness->providerCalls);
        self::assertTrue($result->isVerifiedSuccess());
        self::assertSame(RunStatus::Succeeded, $terminal->status);
        self::assertSame('result:verified', $terminal->terminalResultReference);
    }

    #[Test]
    public function retry_recovery_reaches_one_terminal_run_and_rejects_stale_callbacks(): void
    {
        $first = $this->ready('attempt:1', 0)
            ->acquireLease(new AttemptId('attempt:1'), new LeaseId('lease:1'), new ExecutionNodeRef('node:1'), new LeaseToken('token:1'), 'acquire:1', $this->at(1), 60)
            ->start(new AttemptId('attempt:1'), new LeaseToken('token:1'), $this->at(2));
        $recovering = $first->observeFailure(new AttemptId('attempt:1'), new LeaseToken('token:1'), FailureClassification::Transient, 'failure:1', 'network', $this->at(3), new RetryPolicy(2));
        $second = $recovering->scheduleRetry(new AttemptId('attempt:2'), $this->at(4))
            ->acquireLease(new AttemptId('attempt:2'), new LeaseId('lease:2'), new ExecutionNodeRef('node:2'), new LeaseToken('token:2'), 'acquire:2', $this->at(5), 60)
            ->start(new AttemptId('attempt:2'), new LeaseToken('token:2'), $this->at(6))
            ->finish(new AttemptId('attempt:2'), new LeaseToken('token:2'), RunStatus::Succeeded, $this->at(7), resultReference: 'result:verified');

        self::assertSame(RunStatus::Succeeded, $second->status);
        self::assertCount(2, $second->attempts);
        self::assertSame('attempt:1', $second->attempts[1]->previousAttemptId->value);
        $this->assertRejected(ExecutionStateRejectionReason::AlreadyTerminal, fn () => $second->finish(new AttemptId('attempt:1'), new LeaseToken('token:1'), RunStatus::Succeeded, $this->at(8), resultReference: 'result:verified'));
        $this->assertRejected(ExecutionStateRejectionReason::AlreadyTerminal, fn () => $second->scheduleRetry(new AttemptId('attempt:3'), $this->at(8)));
    }

    #[Test]
    public function lost_ack_and_restart_converge_without_a_second_attempt_or_lease(): void
    {
        $uncertain = $this->ready('attempt:1', 0)
            ->acquireLease(new AttemptId('attempt:1'), new LeaseId('lease:1'), new ExecutionNodeRef('node:1'), new LeaseToken('token:1'), 'acquire:1', $this->at(1), 60)
            ->start(new AttemptId('attempt:1'), new LeaseToken('token:1'), $this->at(2))
            ->observeFailure(new AttemptId('attempt:1'), new LeaseToken('token:1'), FailureClassification::AcknowledgementUncertain, 'ack:lost', 'accepted remotely; acknowledgement lost', $this->at(3), new RetryPolicy(2));
        $store = new InMemoryExecutionStateStore();
        $store->create($uncertain);
        $reloaded = $store->find(new RunId('run:conformance'));
        $running = $reloaded->confirmAcknowledgement(new AttemptId('attempt:1'), new LeaseToken('token:1'), $this->at(4));

        self::assertCount(1, $running->attempts);
        self::assertCount(1, $running->currentAttempt()->leases);
        self::assertSame('running', ExecutionStateReadModel::fromState($running)->status);
    }

    #[Test]
    public function cancellation_and_verification_disposition_remain_independent_and_evidence_bearing(): void
    {
        $running = $this->ready('attempt:1', 0)
            ->acquireLease(new AttemptId('attempt:1'), new LeaseId('lease:1'), new ExecutionNodeRef('node:1'), new LeaseToken('token:1'), 'acquire:1', $this->at(1), 60)
            ->start(new AttemptId('attempt:1'), new LeaseToken('token:1'), $this->at(2));
        $cancelled = $running
            ->requestCancellation('cancel:1', CancellationKind::Manual, 'user:1', 'operator', CancellationAuthorization::allow(), $this->at(3))
            ->confirmCancellation(new AttemptId('attempt:1'), new LeaseToken('token:1'), 'cancel:1', $this->at(4), 'partial:git-and-logs');
        $result = (new PostflightResultAssembler)->assemble(
            RunResult::cancelled(reason: 'operator'),
            new PostflightReport([], 'Partial evidence retained at partial:git-and-logs.', $this->at(5)->format(DATE_ATOM), VerificationStatus::Incomplete),
        );

        self::assertSame(RunStatus::Cancelled, $cancelled->status);
        self::assertSame('partial:git-and-logs', $cancelled->terminalResultReference);
        self::assertSame(RunStatus::Cancelled, $result->status);
        self::assertFalse($result->isVerifiedSuccess());
    }

    private function ready(string $attemptId, int $seconds): ExecutionState
    {
        return ExecutionState::create(new RunId('run:conformance'), $this->at($seconds), RunIdentityFixtures::executionIdentity())->scheduleAttempt(new AttemptId($attemptId), $this->at($seconds));
    }

    private function at(int $seconds): DateTimeImmutable
    {
        return (new DateTimeImmutable('2026-08-29T12:00:00Z'))->modify("+{$seconds} seconds");
    }

    private function assertRejected(ExecutionStateRejectionReason $reason, callable $operation): void
    {
        try {
            $operation();
            self::fail('Expected conformance rejection.');
        } catch (ExecutionStateRejected $rejected) {
            self::assertSame($reason, $rejected->reason);
        }
    }
}

final class AuthorizedRunPreflight implements BeforeTurnHandler
{
    public function __construct(private readonly \Sifrious\Logres\Run $run) {}

    public function handle(RunRequest $request, TurnContext $context): TurnContext
    {
        if ($this->run->dispatchAuthorization === null) {
            throw new \RuntimeException('Dispatch authorization is required before invocation.');
        }
        return $context;
    }
}

final class VerifiedConformancePostflight implements AfterTurnHandler
{
    public function handle(RunRequest $request, TurnContext $context, RunResult $result): RunResult
    {
        return (new PostflightResultAssembler)->assemble($result, new PostflightReport([], 'independently verified', '2026-08-29T12:00:04Z', VerificationStatus::Succeeded));
    }
}

final class ConformanceHarness extends AbstractHarness
{
    public int $providerCalls = 0;

    public function __construct()
    {
        parent::__construct('conformance', new HarnessCapability('conformance', true, true, true, false));
    }

    public function probe(): HarnessProbe
    {
        return HarnessProbe::available(new EnvironmentSnapshot('test', '1', 'host', '/bin/test'));
    }

    protected function startHarness(RunRequest $request, TurnContext $context, ExecutionObserver $observer): HarnessHandle
    {
        $this->providerCalls++;
        return new HarnessHandle('provider:authorized', 'conformance', new DateTimeImmutable('2026-08-29T12:00:03Z'));
    }

    public function status(HarnessHandle $handle, ExecutionObserver $observer): HarnessStatus
    {
        return HarnessStatus::terminal(RunResult::succeeded());
    }

    public function cancel(HarnessHandle $handle): void {}
}

final class ConformanceObserver implements ExecutionObserver
{
    public function contextResolved(TurnContext $context): void {}
    public function processStarted(HarnessHandle $handle): void {}
    public function stdout(string $chunk): void {}
    public function stderr(string $chunk): void {}
    public function artifact(ArtifactReference $artifact): void {}
}
