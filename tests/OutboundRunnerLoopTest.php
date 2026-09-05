<?php

declare(strict_types=1);

namespace Sifrious\Logres\Tests;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sifrious\Logres\Tests\Fixtures\ExecutionRequestFixtures;
use Sifrious\Logres\CapabilitySnapshot;
use Sifrious\Logres\CurrentWorkload;
use Sifrious\Logres\EnvelopeAuthenticator;
use Sifrious\Logres\ExecutionEnvelope;
use Sifrious\Logres\ExecutionRunner;
use Sifrious\Logres\ExecutionTargetId;
use Sifrious\Logres\OutboundRunnerCycleStatus;
use Sifrious\Logres\OutboundRunnerLoop;
use Sifrious\Logres\PlatformIdentity;
use Sifrious\Logres\RepositoryIdentity;
use Sifrious\Logres\RunId;
use Sifrious\Logres\RunnerAuthorization;
use Sifrious\Logres\RunnerAvailability;
use Sifrious\Logres\RunnerDescriptor;
use Sifrious\Logres\RunnerEvent;
use Sifrious\Logres\RunnerEventSink;
use Sifrious\Logres\RunnerIdentity;
use Sifrious\Logres\RunnerLease;
use Sifrious\Logres\RunnerLeaseAcknowledgementStatus;
use Sifrious\Logres\RunnerLeaseStatus;
use Sifrious\Logres\RunnerLifecycle;
use Sifrious\Logres\RunnerLocalRecord;
use Sifrious\Logres\RunnerLocalStage;
use Sifrious\Logres\RunnerPollRequest;
use Sifrious\Logres\RunnerPollResponse;
use Sifrious\Logres\RunnerRuntime;
use Sifrious\Logres\RunnerRuntimeObserver;
use Sifrious\Logres\RunnerTerminalReconciler;
use Sifrious\Logres\RunnerTerminalResultDeliveryStatus;
use Sifrious\Logres\RunnerTerminalStatus;
use Sifrious\Logres\RunnerWorkspace;
use Sifrious\Logres\RuntimeRequest;
use Sifrious\Logres\RuntimeResult;
use Sifrious\Logres\Tests\Fixtures\InMemoryRunnerDispatchContracts;
use Sifrious\Logres\WorkspaceAuthority;
use Sifrious\Logres\WorkspacePath;

final class OutboundRunnerLoopTest extends TestCase
{
    #[Test]
    public function no_work_does_not_acknowledge_or_invoke_runtime(): void
    {
        [$loop, $fixture, $runtime] = $this->loop();
        $fixture->enqueuePollResponse(RunnerPollResponse::noWork(9));

        $result = $loop->run($this->pollRequest());

        self::assertSame(OutboundRunnerCycleStatus::NoWork, $result->status);
        self::assertSame(9, $result->retryAfterSeconds);
        self::assertSame(0, $fixture->acknowledgementCalls);
        self::assertSame(0, $runtime->calls);
        self::assertCount(0, $fixture->reported);
    }

    #[Test]
    public function acknowledged_lease_executes_and_reports_once(): void
    {
        [$loop, $fixture, $runtime] = $this->loop();
        $envelope = $this->enqueueLease($fixture);

        $result = $loop->run($this->pollRequest());

        self::assertSame(OutboundRunnerCycleStatus::Completed, $result->status);
        self::assertSame(RunnerLeaseAcknowledgementStatus::Acknowledged, $result->acknowledgement?->status);
        self::assertSame(1, $runtime->calls);
        self::assertCount(1, $fixture->reported);
        self::assertSame(RunnerLocalStage::Terminal, $fixture->find(RunnerLocalRecord::key($envelope))?->stage);
    }

    #[Test]
    public function duplicate_acknowledgement_never_executes_logical_work_twice(): void
    {
        [$loop, $fixture, $runtime] = $this->loop();
        $this->enqueueLease($fixture);
        $this->enqueueLease($fixture, register: false);

        $first = $loop->run($this->pollRequest());
        $duplicate = $loop->run($this->pollRequest());

        self::assertSame(OutboundRunnerCycleStatus::Completed, $first->status);
        self::assertSame(RunnerLeaseAcknowledgementStatus::Duplicate, $duplicate->acknowledgement?->status);
        self::assertSame(1, $runtime->calls);
    }

    #[Test]
    public function rejected_or_conflicting_acknowledgement_does_not_invoke_runtime(): void
    {
        [$rejectedLoop, $rejectedFixture, $rejectedRuntime] = $this->loop();
        $expired = $this->offeredLease(expiresAt: $this->now()->modify('-1 second'));
        $this->enqueueLease($rejectedFixture, $expired);

        $rejected = $rejectedLoop->run($this->pollRequest());

        self::assertSame(OutboundRunnerCycleStatus::RejectedAck, $rejected->status);
        self::assertSame(RunnerLeaseAcknowledgementStatus::Rejected, $rejected->acknowledgement?->status);
        self::assertSame(0, $rejectedRuntime->calls);

        [$conflictLoop, $conflictFixture, $conflictRuntime] = $this->loop();
        $conflicting = $this->offeredLease(runnerId: 'runner:other');
        $conflictFixture->registerLease($conflicting);
        $envelope = ExecutionEnvelope::parse($this->envelope());
        $conflictFixture->enqueuePollResponse(RunnerPollResponse::lease(
            $envelope,
            $this->offeredLease(),
        ));

        $conflict = $conflictLoop->run($this->pollRequest());

        self::assertSame(OutboundRunnerCycleStatus::RejectedAck, $conflict->status);
        self::assertSame(RunnerLeaseAcknowledgementStatus::Conflict, $conflict->acknowledgement?->status);
        self::assertSame(0, $conflictRuntime->calls);
    }

    #[Test]
    public function retry_receipt_remains_reporting_and_reconciles_without_second_invoke(): void
    {
        [$loop, $fixture, $runtime, $reconciler] = $this->loop();
        $envelope = $this->enqueueLease($fixture);
        $fixture->queueReportStatus(RunnerTerminalResultDeliveryStatus::Retry);
        $fixture->queueReportStatus(RunnerTerminalResultDeliveryStatus::Accepted);

        $result = $loop->run($this->pollRequest());
        $key = RunnerLocalRecord::key($envelope);

        self::assertSame(OutboundRunnerCycleStatus::ReportRetry, $result->status);
        self::assertSame(RunnerLocalStage::Reporting, $fixture->find($key)?->stage);
        self::assertSame(1, $runtime->calls);

        $receipt = $reconciler->reconcile($key, $this->now()->modify('+1 second'));

        self::assertSame(RunnerTerminalResultDeliveryStatus::Accepted, $receipt?->status);
        self::assertSame(RunnerLocalStage::Terminal, $fixture->find($key)?->stage);
        self::assertSame(1, $runtime->calls);
        self::assertCount(2, $fixture->reported);
    }

    #[Test]
    public function execution_envelope_array_round_trip_is_lossless(): void
    {
        $serialized = ExecutionEnvelope::parse($this->envelope())->toArray();

        self::assertSame($serialized, ExecutionEnvelope::parse($serialized)->toArray());
    }

    /**
     * @return array{
     *   OutboundRunnerLoop,
     *   InMemoryRunnerDispatchContracts,
     *   object{calls: int}&RunnerRuntime,
     *   RunnerTerminalReconciler
     * }
     */
    private function loop(): array
    {
        $runtime = new class implements RunnerRuntime {
            public int $calls = 0;
            public function availableAdapters(): array { return ['fake']; }
            public function canInvoke(string $adapter, string $runtime): bool { return $adapter === 'fake' && $runtime === 'agent'; }
            public function invoke(RuntimeRequest $request, RunnerRuntimeObserver $observer): RuntimeResult
            {
                ++$this->calls;

                return new RuntimeResult(RunnerTerminalStatus::Success, 0);
            }
        };
        $fixture = new InMemoryRunnerDispatchContracts();
        $reconciler = new RunnerTerminalReconciler($fixture, $fixture);
        $loop = new OutboundRunnerLoop(
            $fixture,
            $fixture,
            $this->executionRunner($runtime, $fixture),
            $fixture,
            $reconciler,
        );

        return [$loop, $fixture, $runtime, $reconciler];
    }

    private function enqueueLease(
        InMemoryRunnerDispatchContracts $fixture,
        ?RunnerLease $lease = null,
        bool $register = true,
    ): ExecutionEnvelope {
        $lease ??= $this->offeredLease();
        if ($register) {
            $fixture->registerLease($lease);
        }
        $envelope = ExecutionEnvelope::parse($this->envelope());
        $fixture->enqueuePollResponse(RunnerPollResponse::lease($envelope, $lease));

        return $envelope;
    }

    private function offeredLease(
        string $runnerId = 'runner:test',
        ?DateTimeImmutable $expiresAt = null,
    ): RunnerLease {
        return new RunnerLease(
            'lease:test',
            new RunId('run:test'),
            new ExecutionTargetId('target:mac:test'),
            $runnerId,
            RunnerLeaseStatus::Offered,
            $this->now()->modify('-1 minute'),
            $expiresAt ?? $this->now()->modify('+1 minute'),
        );
    }

    private function pollRequest(): RunnerPollRequest
    {
        return new RunnerPollRequest(
            new RunnerIdentity('runner:test'),
            'poll-signed',
            ['1'],
            ['fake'],
            $this->now(),
        );
    }

    private function executionRunner(
        RunnerRuntime $runtime,
        InMemoryRunnerDispatchContracts $state,
    ): ExecutionRunner {
        $auth = new class implements EnvelopeAuthenticator {
            public function authenticates(ExecutionEnvelope $envelope): bool { return $envelope->authenticationMaterial === 'signed'; }
        };
        $authorization = new class implements RunnerAuthorization {
            public function authorizes(ExecutionEnvelope $envelope): bool { return $envelope->authorizationGrantReference === 'grant:test'; }
        };
        $workspace = new class implements RunnerWorkspace {
            public function isAvailable(WorkspaceAuthority $identity): bool { return $identity->value === 'workspace:test'; }
            public function matches(WorkspaceAuthority $identity, WorkspacePath $path, RepositoryIdentity $repository): bool
            {
                return $identity->value === 'workspace:test'
                    && $path->value === '/work/test'
                    && $repository->value === 'repository:example.test/repo';
            }
        };
        $lifecycle = new class implements RunnerLifecycle {
            public function permits(ExecutionEnvelope $envelope, RunnerIdentity $runner, DateTimeImmutable $now): bool { return true; }
            public function cancellationRequested(ExecutionEnvelope $envelope): bool { return false; }
        };
        $events = new class implements RunnerEventSink {
            public function emit(RunnerEvent $event): void {}
        };

        return new ExecutionRunner(
            new RunnerDescriptor(
                new RunnerIdentity('runner:test'),
                new PlatformIdentity('darwin', 'arm64'),
                new CapabilitySnapshot(['agent'], ['fake'], ['1'], $this->now()),
                RunnerAvailability::Available,
                new CurrentWorkload(0, 1),
                ['grant:test'],
                ['workspace:test'],
            ),
            $auth,
            $authorization,
            $workspace,
            $lifecycle,
            $runtime,
            $state,
            $events,
        );
    }

    /** @return array<string, mixed> */
    private function envelope(): array
    {
        return [
            'run_id' => 'run:test',
            'attempt_id' => 'attempt:test',
            'lease_id' => 'lease:test',
            'lease_token' => 'lease-secret',
            'target_runner_id' => 'runner:test',
            'workspace_identity' => 'workspace:test',
            'workspace_path' => '/work/test',
            'repository_identity' => 'repository:example.test/repo',
            'runtime' => 'agent',
            'runtime_adapter' => 'fake',
            'authorization_grant_reference' => 'grant:test',
            'issued_at' => '2026-08-29T11:00:00Z',
            'expires_at' => '2026-08-29T13:00:00Z',
            'protocol_version' => '1',
            'idempotency_identity' => 'dispatch:test',
            'authentication_material' => 'signed',
            'required_capabilities' => ['agent'],
            'request_payload' => ['prompt' => 'ok'],
            'stacks_context' => ExecutionRequestFixtures::executionIdentity(
                'workspace:test',
                '/work/test',
                repositoryId: 'repository:example.test/repo',
                target: 'target:local:test',
                checkoutId: 'checkout:test',
            )->toArray(),
        ];
    }

    private function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-08-29T12:00:00Z');
    }
}
