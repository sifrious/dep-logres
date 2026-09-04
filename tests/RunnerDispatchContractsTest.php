<?php

declare(strict_types=1);

namespace Sifrious\Logres\Tests;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sifrious\Logres\CapabilitySnapshot;
use Sifrious\Logres\CurrentWorkload;
use Sifrious\Logres\EnvelopeAuthenticator;
use Sifrious\Logres\ExecutionEnvelope;
use Sifrious\Logres\ExecutionRunner;
use Sifrious\Logres\ExecutionTargetId;
use Sifrious\Logres\PlatformIdentity;
use Sifrious\Logres\RepositoryIdentity;
use Sifrious\Logres\RunId;
use Sifrious\Logres\RunnerAuthorization;
use Sifrious\Logres\RunnerAvailability;
use Sifrious\Logres\RunnerDescriptor;
use Sifrious\Logres\RunnerIdentity;
use Sifrious\Logres\RunnerLease;
use Sifrious\Logres\RunnerLeaseAcknowledgement;
use Sifrious\Logres\RunnerLeaseAcknowledgementStatus;
use Sifrious\Logres\RunnerLeaseStatus;
use Sifrious\Logres\RunnerLifecycle;
use Sifrious\Logres\RunnerLocalRecord;
use Sifrious\Logres\RunnerLocalStage;
use Sifrious\Logres\RunnerPollRequest;
use Sifrious\Logres\RunnerPollResponse;
use Sifrious\Logres\RunnerRejectionReason;
use Sifrious\Logres\RunnerRuntime;
use Sifrious\Logres\RunnerRuntimeObserver;
use Sifrious\Logres\RunnerTerminalReconciler;
use Sifrious\Logres\RunnerTerminalResult;
use Sifrious\Logres\RunnerTerminalResultDeliveryStatus;
use Sifrious\Logres\RunnerTerminalStatus;
use Sifrious\Logres\RunnerWorkspace;
use Sifrious\Logres\RuntimeRequest;
use Sifrious\Logres\RuntimeResult;
use Sifrious\Logres\WorkspaceAuthority;
use Sifrious\Logres\WorkspacePath;
use Sifrious\Logres\Tests\Fixtures\InMemoryRunnerDispatchContracts;

final class RunnerDispatchContractsTest extends TestCase
{
    #[Test]
    public function poll_returns_no_work_with_bounded_backoff(): void
    {
        $fixture = new InMemoryRunnerDispatchContracts();
        $response = $fixture->poll($this->pollRequest());

        self::assertSame('no_work', $response->status->value);
        self::assertSame(15, $response->retryAfterSeconds);
        self::assertNull($response->lease);
    }

    #[Test]
    public function lease_acknowledgement_is_idempotent(): void
    {
        $fixture = new InMemoryRunnerDispatchContracts();
        $lease = $this->offeredLease();
        $fixture->registerLease($lease);
        $fixture->enqueuePollResponse(RunnerPollResponse::lease(ExecutionEnvelope::parse($this->envelope()), $lease));
        $response = $fixture->poll($this->pollRequest());

        $first = $fixture->acknowledge(new RunnerLeaseAcknowledgement($response->lease->id, new RunnerIdentity('runner:test'), 'ack:1', $this->now()));
        $duplicate = $fixture->acknowledge(new RunnerLeaseAcknowledgement($response->lease->id, new RunnerIdentity('runner:test'), 'ack:1', $this->now()->modify('+5 seconds')));

        self::assertSame(RunnerLeaseAcknowledgementStatus::Acknowledged, $first->status);
        self::assertSame(RunnerLeaseAcknowledgementStatus::Duplicate, $duplicate->status);
    }

    #[Test]
    public function duplicate_lease_delivery_does_not_invoke_runtime_twice(): void
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
        $state = new InMemoryRunnerDispatchContracts();
        $runner = $this->runner($runtime, $state);

        $first = $runner->execute($this->envelope(), $this->now());
        $second = $runner->execute($this->envelope(), $this->now()->modify('+1 second'));

        self::assertTrue($first->acceptance->accepted);
        self::assertSame($first->terminalResult, $second->terminalResult);
        self::assertSame(1, $runtime->calls);
    }

    #[Test]
    public function reconcile_after_network_loss_promotes_reporting_to_terminal(): void
    {
        $fixture = new InMemoryRunnerDispatchContracts();
        $fixture->queueReportStatus(RunnerTerminalResultDeliveryStatus::Accepted);
        $envelope = ExecutionEnvelope::parse($this->envelope());
        $terminal = new RunnerTerminalResult(
            $envelope->runId,
            $envelope->attemptId,
            $envelope->leaseId,
            new RunnerIdentity('runner:test'),
            RunnerTerminalStatus::Success,
            $envelope->runtime,
            $envelope->runtimeAdapter,
            $envelope->workspaceIdentity,
            $this->now(),
            $this->now(),
            0,
        );
        $key = RunnerLocalRecord::key($envelope);
        $fixture->putReportingRecord(new RunnerLocalRecord(
            $key,
            $envelope->idempotencyIdentity,
            RunnerLocalRecord::fingerprint($envelope),
            RunnerLocalStage::Reporting,
            $this->now(),
            $terminal,
        ));

        $receipt = (new RunnerTerminalReconciler($fixture, $fixture))->reconcile($key, $this->now()->modify('+2 seconds'));

        self::assertNotNull($receipt);
        self::assertSame(RunnerTerminalResultDeliveryStatus::Accepted, $receipt->status);
        self::assertSame(RunnerLocalStage::Terminal, $fixture->find($key)?->stage);
        self::assertCount(1, $fixture->reported);
    }

    #[Test]
    public function workspace_path_outside_the_allowlist_is_rejected_even_with_matching_identities(): void
    {
        $runtime = new class implements RunnerRuntime {
            public int $calls = 0;
            public function availableAdapters(): array { return ['fake']; }
            public function canInvoke(string $adapter, string $runtime): bool { return true; }
            public function invoke(RuntimeRequest $request, RunnerRuntimeObserver $observer): RuntimeResult
            {
                ++$this->calls;
                return new RuntimeResult(RunnerTerminalStatus::Success, 0);
            }
        };
        $state = new InMemoryRunnerDispatchContracts();
        $runner = $this->runner($runtime, $state);
        $escaped = array_replace($this->envelope(), ['workspace_path' => '/work/other/project']);

        $outcome = $runner->execute($escaped, $this->now());

        self::assertFalse($outcome->acceptance->accepted);
        self::assertSame(RunnerRejectionReason::WorkspaceMismatch, $outcome->acceptance->reason);
        self::assertSame(0, $runtime->calls);
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

    private function offeredLease(): RunnerLease
    {
        return new RunnerLease(
            'lease:test',
            new RunId('run:test'),
            new ExecutionTargetId('target:mac:test'),
            'runner:test',
            RunnerLeaseStatus::Offered,
            $this->now(),
            $this->now()->modify('+60 seconds'),
        );
    }

    private function runner(RunnerRuntime $runtime, InMemoryRunnerDispatchContracts $state): ExecutionRunner
    {
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
                if ($identity->value !== 'workspace:test' || $repository->value !== 'repository:example.test/repo') {
                    return false;
                }

                return (new WorkspacePath('/work/test'))->contains($path);
            }
        };
        $lifecycle = new class implements RunnerLifecycle {
            public function permits(ExecutionEnvelope $envelope, RunnerIdentity $runner, DateTimeImmutable $now): bool { return true; }
            public function cancellationRequested(ExecutionEnvelope $envelope): bool { return false; }
        };
        $events = new class implements \Sifrious\Logres\RunnerEventSink {
            public function emit(\Sifrious\Logres\RunnerEvent $event): void {}
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
            'workspace_path' => '/work/test/project',
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
        ];
    }

    private function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-08-29T12:00:00Z');
    }
}
