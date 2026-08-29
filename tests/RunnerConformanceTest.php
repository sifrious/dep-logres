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
use Sifrious\Logres\ExecutionState;
use Sifrious\Logres\ExecutionStateRunnerLifecycle;
use Sifrious\Logres\ExecutionNodeRef;
use Sifrious\Logres\RunId;
use Sifrious\Logres\AttemptId;
use Sifrious\Logres\LeaseId;
use Sifrious\Logres\LeaseToken;
use Sifrious\Logres\PlatformIdentity;
use Sifrious\Logres\RunnerAuthorization;
use Sifrious\Logres\RunnerAvailability;
use Sifrious\Logres\RunnerDescriptor;
use Sifrious\Logres\RunnerEvent;
use Sifrious\Logres\RunnerEventSink;
use Sifrious\Logres\RunnerEventType;
use Sifrious\Logres\RunnerIdentity;
use Sifrious\Logres\RunnerLifecycle;
use Sifrious\Logres\RunnerLocalRecord;
use Sifrious\Logres\RunnerLocalStage;
use Sifrious\Logres\RunnerLocalStateStore;
use Sifrious\Logres\RunnerRejectionReason;
use Sifrious\Logres\RunnerRuntime;
use Sifrious\Logres\RunnerRuntimeObserver;
use Sifrious\Logres\RunnerTerminalStatus;
use Sifrious\Logres\RunnerWorkspace;
use Sifrious\Logres\RuntimeRequest;
use Sifrious\Logres\RuntimeResult;
use Sifrious\Logres\WorkspaceAuthority;
use Sifrious\Logres\WorkspacePath;
use Sifrious\Logres\RepositoryIdentity;
use Sifrious\Logres\Tests\Fixtures\InMemoryExecutionStateStore;

final class RunnerConformanceTest extends TestCase
{
    #[Test]
    public function runner_can_be_represented_provider_neutrally(): void
    {
        $descriptor = $this->descriptor();
        self::assertSame('runner:test', $descriptor->identity->value);
        self::assertSame(['fake'], $descriptor->capabilities->runtimeAdapters);
        self::assertSame(RunnerAvailability::Available, $descriptor->availability);
    }

    #[Test]
    public function valid_signed_envelope_invokes_wardrobe_contract_once_and_emits_normalized_events(): void
    {
        [$runner, $runtime, $events] = $this->runner();
        $outcome = $runner->execute($this->envelope(), $this->now());

        self::assertTrue($outcome->acceptance->accepted);
        self::assertSame(RunnerTerminalStatus::Success, $outcome->terminalResult->status);
        self::assertSame(1, $runtime->calls);
        self::assertInstanceOf(RuntimeRequest::class, $runtime->lastRequest);
        self::assertSame('run:test', $runtime->lastRequest->runId->value);
        self::assertSame(
            ['accepted', 'starting', 'running', 'status', 'output', 'question', 'intervention_required', 'artifact_reference', 'terminal_result'],
            array_map(static fn (RunnerEvent $event): string => $event->type->value, $events->events),
        );
        self::assertCount(count($events->events), array_unique(array_map(static fn (RunnerEvent $event): string => $event->id, $events->events)));
    }

    #[Test]
    public function rejected_envelopes_fail_closed_and_never_invoke_runtime(): void
    {
        $cases = [
            RunnerRejectionReason::Malformed->value => fn (array $e): array => array_diff_key($e, ['run_id' => true]),
            RunnerRejectionReason::WrongRunner->value => fn (array $e): array => array_replace($e, ['target_runner_id' => 'runner:other']),
            RunnerRejectionReason::Expired->value => fn (array $e): array => array_replace($e, ['expires_at' => '2026-08-29T11:59:59Z']),
            RunnerRejectionReason::Unauthenticated->value => fn (array $e): array => array_replace($e, ['authentication_material' => 'bad']),
            RunnerRejectionReason::UnsupportedProtocolVersion->value => fn (array $e): array => array_replace($e, ['protocol_version' => '99']),
            RunnerRejectionReason::Unauthorized->value => fn (array $e): array => array_replace($e, ['authorization_grant_reference' => 'grant:denied']),
            RunnerRejectionReason::WorkspaceUnavailable->value => fn (array $e): array => array_replace($e, ['workspace_identity' => 'workspace:missing']),
            RunnerRejectionReason::WorkspaceMismatch->value => fn (array $e): array => array_replace($e, ['repository_identity' => 'repository:example.test/other']),
            RunnerRejectionReason::RuntimeUnavailable->value => fn (array $e): array => array_replace($e, ['runtime_adapter' => 'missing']),
            RunnerRejectionReason::CapabilityMismatch->value => fn (array $e): array => array_replace($e, ['required_capabilities' => ['agent', 'gpu']]),
            RunnerRejectionReason::InvalidLifecycleState->value => fn (array $e): array => array_replace($e, ['lease_token' => 'invalid-lifecycle']),
        ];

        foreach ($cases as $expected => $mutate) {
            [$runner, $runtime] = $this->runner();
            $outcome = $runner->execute($mutate($this->envelope()), $this->now());
            self::assertFalse($outcome->acceptance->accepted, $expected);
            self::assertSame($expected, $outcome->acceptance->reason?->value);
            self::assertSame(0, $runtime->calls, $expected);
        }
    }

    #[Test]
    public function duplicate_delivery_and_restart_do_not_duplicate_runtime_invocation(): void
    {
        [$runner, $runtime, , $state] = $this->runner();
        $first = $runner->execute($this->envelope(), $this->now());
        $second = $runner->execute($this->envelope(), $this->now());
        self::assertSame(1, $runtime->calls);
        self::assertSame($first->terminalResult, $second->terminalResult);

        $envelope = ExecutionEnvelope::parse(array_replace($this->envelope(), ['run_id' => 'run:restart', 'attempt_id' => 'attempt:restart', 'lease_id' => 'lease:restart']));
        $state->save(new RunnerLocalRecord(RunnerLocalRecord::key($envelope), $envelope->idempotencyIdentity, RunnerLocalStage::Invoking, $this->now()));
        $restarted = $runner->execute(array_replace($this->envelope(), ['run_id' => 'run:restart', 'attempt_id' => 'attempt:restart', 'lease_id' => 'lease:restart']), $this->now());
        self::assertSame(RunnerRejectionReason::DuplicateOrAlreadyProcessed, $restarted->acceptance->reason);
        self::assertSame(1, $runtime->calls);
    }

    #[Test]
    public function runner_reports_a_typed_failure_terminal_result(): void
    {
        [$runner, $runtime] = $this->runner();
        $runtime->result = new RuntimeResult(RunnerTerminalStatus::Failure, 17, 'provider_exit', 'Runtime exited unsuccessfully.');

        $outcome = $runner->execute($this->envelope(), $this->now());

        self::assertTrue($outcome->acceptance->accepted);
        self::assertSame(RunnerTerminalStatus::Failure, $outcome->terminalResult->status);
        self::assertSame(17, $outcome->terminalResult->exitCode);
        self::assertSame('provider_exit', $outcome->terminalResult->failureCategory);
    }

    #[Test]
    public function runner_consumes_canonical_logres_attempt_and_lease_authority(): void
    {
        $state = ExecutionState::create(new RunId('run:test'), $this->now())
            ->scheduleAttempt(new AttemptId('attempt:test'), $this->now())
            ->acquireLease(new AttemptId('attempt:test'), new LeaseId('lease:test'), new ExecutionNodeRef('runner:test'), new LeaseToken('lease-secret'), 'dispatch:test', $this->now(), 60);
        $store = new InMemoryExecutionStateStore();
        $store->create($state);
        $lifecycle = new ExecutionStateRunnerLifecycle($store);
        $envelope = ExecutionEnvelope::parse($this->envelope());

        self::assertTrue($lifecycle->permits($envelope, new RunnerIdentity('runner:test'), $this->now()));
        $foreign = ExecutionEnvelope::parse(array_replace($this->envelope(), ['lease_token' => 'foreign']));
        self::assertFalse($lifecycle->permits($foreign, new RunnerIdentity('runner:test'), $this->now()));
    }

    #[Test]
    public function runner_requires_no_inbound_listener(): void
    {
        $reflection = new \ReflectionClass(ExecutionRunner::class);
        $dependencies = array_map(static fn ($parameter): ?string => $parameter->getType()?->getName(), $reflection->getConstructor()->getParameters());
        self::assertNotContains('Illuminate\\Http\\Request', $dependencies);
        self::assertNotContains('Symfony\\Component\\HttpFoundation\\Request', $dependencies);
    }

    private function runner(): array
    {
        $runtime = new class implements RunnerRuntime {
            public int $calls = 0;
            public ?RuntimeRequest $lastRequest = null;
            public RuntimeResult $result;
            public function __construct() { $this->result = new RuntimeResult(RunnerTerminalStatus::Success, 0); }
            public function availableAdapters(): array { return ['fake']; }
            public function canInvoke(string $adapter, string $runtime): bool { return $adapter === 'fake' && $runtime === 'agent'; }
            public function invoke(RuntimeRequest $request, RunnerRuntimeObserver $observer): RuntimeResult
            {
                ++$this->calls; $this->lastRequest = $request;
                $observer->event(RunnerEventType::Status, ['message' => 'working']);
                $observer->event(RunnerEventType::Output, ['stream' => 'stdout', 'chunk' => 'ok']);
                $observer->event(RunnerEventType::Question, ['prompt' => 'continue?']);
                $observer->event(RunnerEventType::InterventionRequired, ['reason' => 'approval']);
                $observer->event(RunnerEventType::ArtifactReference, ['id' => 'artifact:1']);
                return $this->result;
            }
        };
        $state = new class implements RunnerLocalStateStore {
            /** @var array<string, RunnerLocalRecord> */ public array $records = [];
            public function find(string $executionKey): ?RunnerLocalRecord { return $this->records[$executionKey] ?? null; }
            public function save(RunnerLocalRecord $record): void { $this->records[$record->executionKey] = $record; }
        };
        $events = new class implements RunnerEventSink {
            /** @var list<RunnerEvent> */ public array $events = [];
            public function emit(RunnerEvent $event): void { $this->events[] = $event; }
        };
        $auth = new class implements EnvelopeAuthenticator { public function authenticates(ExecutionEnvelope $e): bool { return $e->authenticationMaterial === 'signed'; } };
        $authorization = new class implements RunnerAuthorization { public function authorizes(ExecutionEnvelope $e): bool { return $e->authorizationGrantReference === 'grant:test'; } };
        $workspace = new class implements RunnerWorkspace {
            public function isAvailable(WorkspaceAuthority $identity): bool { return $identity->value === 'workspace:test'; }
            public function matches(WorkspaceAuthority $identity, WorkspacePath $path, RepositoryIdentity $repository): bool { return $path->value === '/work/test' && $repository->value === 'repository:example.test/repo'; }
        };
        $lifecycle = new class implements RunnerLifecycle {
            public function permits(ExecutionEnvelope $e, RunnerIdentity $r, DateTimeImmutable $now): bool { return $e->leaseToken->value !== 'invalid-lifecycle'; }
            public function cancellationRequested(ExecutionEnvelope $e): bool { return false; }
        };

        return [$this->runnerWith($runtime, $state, $events, $auth, $authorization, $workspace, $lifecycle), $runtime, $events, $state];
    }

    private function runnerWith(RunnerRuntime $runtime, RunnerLocalStateStore $state, RunnerEventSink $events, ?EnvelopeAuthenticator $auth = null, ?RunnerAuthorization $authorization = null, ?RunnerWorkspace $workspace = null, ?RunnerLifecycle $lifecycle = null): ExecutionRunner
    {
        $auth ??= new class implements EnvelopeAuthenticator { public function authenticates(ExecutionEnvelope $e): bool { return $e->authenticationMaterial === 'signed'; } };
        $authorization ??= new class implements RunnerAuthorization { public function authorizes(ExecutionEnvelope $e): bool { return $e->authorizationGrantReference === 'grant:test'; } };
        $workspace ??= new class implements RunnerWorkspace {
            public function isAvailable(WorkspaceAuthority $identity): bool { return $identity->value === 'workspace:test'; }
            public function matches(WorkspaceAuthority $identity, WorkspacePath $path, RepositoryIdentity $repository): bool { return $path->value === '/work/test' && $repository->value === 'repository:example.test/repo'; }
        };
        $lifecycle ??= new class implements RunnerLifecycle {
            public function permits(ExecutionEnvelope $e, RunnerIdentity $r, DateTimeImmutable $now): bool { return $e->leaseToken->value !== 'invalid-lifecycle'; }
            public function cancellationRequested(ExecutionEnvelope $e): bool { return false; }
        };
        return new ExecutionRunner($this->descriptor(), $auth, $authorization, $workspace, $lifecycle, $runtime, $state, $events);
    }

    private function descriptor(): RunnerDescriptor
    {
        return new RunnerDescriptor(new RunnerIdentity('runner:test'), new PlatformIdentity('test-os', 'test-arch'), new CapabilitySnapshot(['agent'], ['fake'], ['1'], $this->now()), RunnerAvailability::Available, new CurrentWorkload(0, 1), ['grant:test']);
    }

    private function envelope(): array
    {
        return [
            'run_id' => 'run:test', 'attempt_id' => 'attempt:test', 'lease_id' => 'lease:test', 'lease_token' => 'lease-secret',
            'target_runner_id' => 'runner:test', 'workspace_identity' => 'workspace:test', 'workspace_path' => '/work/test',
            'repository_identity' => 'repository:example.test/repo', 'runtime' => 'agent', 'runtime_adapter' => 'fake',
            'authorization_grant_reference' => 'grant:test', 'issued_at' => '2026-08-29T11:00:00Z', 'expires_at' => '2026-08-29T13:00:00Z',
            'protocol_version' => '1', 'idempotency_identity' => 'dispatch:test', 'authentication_material' => 'signed',
            'required_capabilities' => ['agent'], 'request_payload' => ['prompt' => 'return ok'],
        ];
    }

    private function now(): DateTimeImmutable { return new DateTimeImmutable('2026-08-29T12:00:00Z'); }
}
