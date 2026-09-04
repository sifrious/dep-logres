<?php

declare(strict_types=1);

namespace Sifrious\Logres\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use InvalidArgumentException;
use Sifrious\Logres\AttemptId;
use Sifrious\Logres\ProviderBindingOutcome;
use Sifrious\Logres\ProviderBindingStatus;
use Sifrious\Logres\ProviderDispatch;
use Sifrious\Logres\ProviderDispatchResult;
use Sifrious\Logres\ProviderExecutionLookup;
use Sifrious\Logres\ProviderExecutionLookupResult;
use Sifrious\Logres\ProviderInvocationCoordinator;
use Sifrious\Logres\ProviderInvocationRecord;
use Sifrious\Logres\ProviderInvocationRequest;
use Sifrious\Logres\ProviderInvocationReservation;
use Sifrious\Logres\ProviderInvocationStatus;
use Sifrious\Logres\ProviderInvocationPersistence;
use Sifrious\Logres\ProviderLookupStatus;
use Sifrious\Logres\ProviderAcknowledgement;
use Sifrious\Logres\ProviderExecutionId;
use Sifrious\Logres\ExecutionTargetId;
use Sifrious\Logres\Run;
use Sifrious\Logres\TaskPrompt;
use Sifrious\Logres\TaskPromptCompiler;
use Sifrious\Logres\Tests\Fixtures\RunIdentityFixtures;
use Sifrious\Logres\Tests\Fixtures\TaskPromptFixtures;

final class ProviderInvocationCoordinatorTest extends TestCase
{
    #[Test]
    public function immutable_request_contains_the_complete_provider_neutral_dispatch_contract(): void
    {
        [$run, $prompt, $request] = $this->fixture();

        self::assertSame($run->provenance->requestId, $request->requestId);
        self::assertSame($run->provenance->taskId, $request->taskId);
        self::assertSame('target:orbs:orb-a', $request->targetId->value);
        self::assertSame('orbs', $request->provider);
        self::assertSame('codex', $request->agentAdapter);
        self::assertSame($prompt, $request->prompt);
        self::assertStringStartsWith('# Task execution prompt', $request->prompt->compiledPrompt);
        self::assertSame(['Use the frozen workspace only.'], $request->workspaceInstructions);
        self::assertSame('/events', $request->eventDelivery['callback']);
        self::assertSame('/input', $request->inputResponse['callback']);
        self::assertSame(900, $request->timeoutSeconds);
        self::assertSame('cancel:invocation-001', $request->cancellationReference);
    }

    #[Test]
    public function accepted_dispatch_persists_intent_before_exactly_one_provider_call_and_binds_acknowledgement(): void
    {
        [$run, , $request] = $this->fixture();
        $provider = new FakeProviderDispatch(ProviderDispatchResult::accepted(RunIdentityFixtures::acknowledgement()));
        [$coordinator, $runs, $invocations] = $this->coordinator($run, $provider);

        $result = $coordinator->dispatch($run, $request, RunIdentityFixtures::DISPATCHED_AT);

        self::assertSame(1, $provider->calls);
        self::assertNotNull($provider->recordObservedDuringCall);
        self::assertSame(ProviderInvocationStatus::Dispatching, $provider->recordObservedDuringCall?->status);
        self::assertSame(ProviderInvocationStatus::Accepted, $result->status);
        self::assertSame(ProviderBindingStatus::Acknowledged, $runs->find($run->id)?->providerBindingStatus);
        self::assertSame('orbs:execution-001', $result->run->providerExecutionId?->canonical());
        self::assertSame(ProviderInvocationStatus::Accepted, $invocations->find($request->invocationId)?->status);
    }

    #[Test]
    public function duplicate_job_returns_the_durable_record_without_calling_provider_twice(): void
    {
        [$run, , $request] = $this->fixture();
        $provider = new FakeProviderDispatch(ProviderDispatchResult::accepted(RunIdentityFixtures::acknowledgement()));
        [$coordinator] = $this->coordinator($run, $provider);

        $first = $coordinator->dispatch($run, $request, RunIdentityFixtures::DISPATCHED_AT);
        $duplicate = $coordinator->dispatch($first->run, $request, RunIdentityFixtures::DISPATCHED_AT);

        self::assertSame(1, $provider->calls);
        self::assertSame(ProviderInvocationStatus::Duplicate, $duplicate->status);
        self::assertSame(ProviderInvocationStatus::Accepted, $duplicate->record->status);
    }

    #[Test]
    public function rejected_and_unavailable_dispatches_are_durable_and_never_acknowledged(): void
    {
        foreach ([
            ProviderInvocationStatus::Rejected->value => ProviderDispatchResult::rejected('invalid request'),
            ProviderInvocationStatus::Unavailable->value => ProviderDispatchResult::unavailable('provider offline'),
        ] as $expected => $providerResult) {
            [$run, , $request] = $this->fixture($expected);
            $provider = new FakeProviderDispatch($providerResult);
            [$coordinator, $runs, $invocations] = $this->coordinator($run, $provider);

            $result = $coordinator->dispatch($run, $request, RunIdentityFixtures::DISPATCHED_AT);

            self::assertSame($expected, $result->status->value);
            self::assertSame(ProviderBindingStatus::DispatchFailed, $runs->find($run->id)?->providerBindingStatus);
            self::assertSame($expected, $invocations->find($request->invocationId)?->status->value);
        }
    }

    #[Test]
    public function accepted_remotely_but_lost_locally_reconciles_without_a_second_dispatch(): void
    {
        [$run, , $request] = $this->fixture('lost');
        $provider = new FakeProviderDispatch(ProviderDispatchResult::acknowledgementUncertain('response lost'));
        [$coordinator, $runs] = $this->coordinator($run, $provider);
        $uncertain = $coordinator->dispatch($run, $request, RunIdentityFixtures::DISPATCHED_AT);
        $lookup = new class implements ProviderExecutionLookup {
            public function find(Run $run): ProviderExecutionLookupResult
            {
                return new ProviderExecutionLookupResult(ProviderLookupStatus::Found, RunIdentityFixtures::acknowledgement('execution-lost'));
            }
        };

        $reconciled = $coordinator->reconcile($uncertain->run, $lookup);

        self::assertSame(1, $provider->calls);
        self::assertSame(ProviderBindingOutcome::Acknowledged, $reconciled->outcome);
        self::assertSame('orbs:execution-lost', $runs->find($run->id)?->providerExecutionId?->canonical());
        self::assertSame(ProviderInvocationStatus::Accepted, $runs->find($request->invocationId)?->status);
        self::assertSame('orbs:execution-lost', $runs->find($request->invocationId)?->acknowledgement?->providerExecutionId->canonical());
    }

    #[Test]
    public function provider_lookup_unavailable_remains_explicitly_reconcilable_without_redispatch(): void
    {
        [$run, , $request] = $this->fixture('lookup-unavailable');
        $provider = new FakeProviderDispatch(ProviderDispatchResult::acknowledgementUncertain('response lost'));
        [$coordinator] = $this->coordinator($run, $provider);
        $uncertain = $coordinator->dispatch($run, $request, RunIdentityFixtures::DISPATCHED_AT);
        $lookup = new class implements ProviderExecutionLookup {
            public function find(Run $run): ProviderExecutionLookupResult
            {
                return new ProviderExecutionLookupResult(ProviderLookupStatus::Unavailable, reason: 'lookup offline');
            }
        };

        $reconciled = $coordinator->reconcile($uncertain->run, $lookup);

        self::assertSame(1, $provider->calls);
        self::assertSame(ProviderBindingOutcome::ReconciliationRequired, $reconciled->outcome);
        self::assertSame('provider_lookup_unavailable', $reconciled->failure?->code);
    }

    #[Test]
    public function dispatch_rejects_a_prompt_or_adapter_not_frozen_in_run_provenance(): void
    {
        [$run, $prompt, $request] = $this->fixture('identity');
        $changedPrompt = (new TaskPromptCompiler('logres-task-prompt-v2'))->compile(TaskPromptFixtures::input(), $prompt);
        $changed = $this->request($run, $changedPrompt, 'identity');
        $provider = new FakeProviderDispatch(ProviderDispatchResult::accepted(RunIdentityFixtures::acknowledgement()));
        [$coordinator] = $this->coordinator($run, $provider);

        try {
            $coordinator->dispatch($run, $changed, RunIdentityFixtures::DISPATCHED_AT);
            self::fail('Changed prompt must be rejected.');
        } catch (InvalidArgumentException) {
            self::assertSame(0, $provider->calls);
        }

        $wrongAdapter = $this->request($run, $prompt, 'identity', 'amp');
        $this->expectException(InvalidArgumentException::class);
        $coordinator->dispatch($run, $wrongAdapter, RunIdentityFixtures::DISPATCHED_AT);
    }

    #[Test]
    public function provider_exception_becomes_durable_uncertain_and_reconciles_without_redispatch(): void
    {
        [$run, , $request] = $this->fixture('exception');
        $provider = new FakeProviderDispatch(new \RuntimeException('connection reset'));
        [$coordinator, , $invocations] = $this->coordinator($run, $provider);

        $uncertain = $coordinator->dispatch($run, $request, RunIdentityFixtures::DISPATCHED_AT);
        self::assertSame(ProviderBindingStatus::AcknowledgementUncertain, $uncertain->run->providerBindingStatus);
        self::assertSame(ProviderInvocationStatus::AcknowledgementUncertain, $invocations->find($request->invocationId)?->status);

        $lookup = new class implements ProviderExecutionLookup {
            public function find(Run $run): ProviderExecutionLookupResult
            {
                return new ProviderExecutionLookupResult(ProviderLookupStatus::Found, RunIdentityFixtures::acknowledgement('execution-exception'));
            }
        };
        $coordinator->reconcile($uncertain->run, $lookup);
        self::assertSame(1, $provider->calls);
    }

    #[Test]
    public function invalid_run_transition_does_not_reserve_an_invocation(): void
    {
        [$run, , $request] = $this->fixture('invalid-transition');
        $provider = new FakeProviderDispatch(ProviderDispatchResult::accepted(RunIdentityFixtures::acknowledgement()));
        [$coordinator, , $invocations] = $this->coordinator($run, $provider);
        $acknowledged = (new \Sifrious\Logres\ProviderExecutionBinder)->acknowledge(
            $run->awaitingAcknowledgement(RunIdentityFixtures::DISPATCHED_AT),
            RunIdentityFixtures::acknowledgement(),
        )->run;

        try {
            $coordinator->dispatch($acknowledged, $request, RunIdentityFixtures::DISPATCHED_AT);
            self::fail('Already dispatched Run must be rejected.');
        } catch (InvalidArgumentException) {
            self::assertNull($invocations->find($request->invocationId));
            self::assertSame(0, $provider->calls);
        }
    }

    #[Test]
    public function reconciliation_is_guarded_outside_uncertain_states(): void
    {
        [$run] = $this->fixture('guard');
        $provider = new FakeProviderDispatch(ProviderDispatchResult::unavailable('unused'));
        [$coordinator] = $this->coordinator($run, $provider);
        $lookup = new class implements ProviderExecutionLookup {
            public int $calls = 0;
            public function find(Run $run): ProviderExecutionLookupResult
            {
                ++$this->calls;
                return new ProviderExecutionLookupResult(ProviderLookupStatus::Unavailable, reason: 'offline');
            }
        };

        $result = $coordinator->reconcile($run, $lookup);

        self::assertSame(ProviderBindingOutcome::Conflict, $result->outcome);
        self::assertSame('run_not_reconcilable', $result->failure?->code);
        self::assertSame(0, $lookup->calls);
    }

    #[Test]
    public function accepted_acknowledgement_conflict_is_not_reported_as_accepted(): void
    {
        [$run, , $request] = $this->fixture('binding-conflict');
        $ack = new ProviderAcknowledgement(new ProviderExecutionId('orbs', 'wrong-target'), new ExecutionTargetId('target:orbs:orb-b'), RunIdentityFixtures::ACKNOWLEDGED_AT);
        $provider = new FakeProviderDispatch(ProviderDispatchResult::accepted($ack));
        [$coordinator, , $invocations] = $this->coordinator($run, $provider);

        $outcome = $coordinator->dispatch($run, $request, RunIdentityFixtures::DISPATCHED_AT);

        self::assertSame(ProviderInvocationStatus::BindingConflict, $outcome->status);
        self::assertSame(ProviderBindingStatus::ConflictingAcknowledgement, $outcome->run->providerBindingStatus);
        self::assertSame(ProviderInvocationStatus::BindingConflict, $invocations->find($request->invocationId)?->status);
    }

    #[Test]
    public function failed_atomic_reservation_persists_neither_invocation_nor_awaiting_run(): void
    {
        [$run, , $request] = $this->fixture('reserve-crash');
        $provider = new FakeProviderDispatch(ProviderDispatchResult::accepted(RunIdentityFixtures::acknowledgement()));
        [$coordinator, $persistence] = $this->coordinator($run, $provider);
        $persistence->failReservation = true;

        try {
            $coordinator->dispatch($run, $request, RunIdentityFixtures::DISPATCHED_AT);
            self::fail('Atomic reservation failure must surface.');
        } catch (\RuntimeException) {
            self::assertNull($persistence->find($request->invocationId));
            self::assertSame(ProviderBindingStatus::NotDispatched, $persistence->find($run->id)?->providerBindingStatus);
            self::assertSame(0, $provider->calls);
        }
    }

    #[Test]
    public function replay_resumes_a_reserved_invocation_that_never_crossed_provider_boundary(): void
    {
        [$run, , $request] = $this->fixture('reserved-replay');
        $provider = new FakeProviderDispatch(ProviderDispatchResult::accepted(RunIdentityFixtures::acknowledgement('reserved-replay')));
        [$coordinator, $persistence] = $this->coordinator($run, $provider);
        $persistence->reserve($run->awaitingAcknowledgement(RunIdentityFixtures::DISPATCHED_AT), $request);

        $outcome = $coordinator->dispatch($run, $request, RunIdentityFixtures::DISPATCHED_AT);

        self::assertSame(1, $provider->calls);
        self::assertSame(ProviderInvocationStatus::Accepted, $outcome->status);
        self::assertSame(ProviderBindingStatus::Acknowledged, $persistence->find($run->id)?->providerBindingStatus);
    }

    #[Test]
    public function crash_after_provider_acceptance_leaves_both_sides_dispatching_and_replay_never_redispatches(): void
    {
        [$run, , $request] = $this->fixture('accepted-crash');
        $provider = new FakeProviderDispatch(ProviderDispatchResult::accepted(RunIdentityFixtures::acknowledgement('accepted-crash')));
        [$coordinator, $persistence] = $this->coordinator($run, $provider);
        $persistence->failTransitionNumber = 2;

        try {
            $coordinator->dispatch($run, $request, RunIdentityFixtures::DISPATCHED_AT);
            self::fail('Atomic completion failure must surface.');
        } catch (\RuntimeException) {
            self::assertSame(ProviderInvocationStatus::Dispatching, $persistence->find($request->invocationId)?->status);
            self::assertSame(ProviderBindingStatus::AwaitingAcknowledgement, $persistence->find($run->id)?->providerBindingStatus);
        }

        $replay = $coordinator->dispatch($run, $request, RunIdentityFixtures::DISPATCHED_AT);
        self::assertSame(1, $provider->calls);
        self::assertSame(ProviderInvocationStatus::AcknowledgementUncertain, $replay->status);
        self::assertSame(ProviderBindingStatus::AcknowledgementUncertain, $persistence->find($run->id)?->providerBindingStatus);
        self::assertSame(ProviderInvocationStatus::AcknowledgementUncertain, $persistence->find($request->invocationId)?->status);
    }

    #[Test]
    public function dispatching_replay_cannot_overwrite_a_concurrently_accepted_invocation(): void
    {
        [$run, , $request] = $this->fixture('dispatch-race');
        $provider = new FakeProviderDispatch(ProviderDispatchResult::unavailable('must not be called'));
        [$coordinator, $persistence] = $this->coordinator($run, $provider);
        $awaiting = $run->awaitingAcknowledgement(RunIdentityFixtures::DISPATCHED_AT);
        $reserved = $persistence->reserve($awaiting, $request)->record;
        $dispatching = $reserved->dispatching();
        self::assertTrue($persistence->transition($dispatching, $awaiting, $reserved->status, $reserved->version));
        $ack = RunIdentityFixtures::acknowledgement('dispatch-race');
        $persistence->beforeCas = function () use ($persistence, $dispatching, $awaiting, $ack): void {
            $acceptedRun = (new \Sifrious\Logres\ProviderExecutionBinder)->acknowledge($awaiting, $ack)->run;
            $persistence->force($dispatching->record(ProviderDispatchResult::accepted($ack)), $acceptedRun);
        };

        $outcome = $coordinator->dispatch($run, $request, RunIdentityFixtures::DISPATCHED_AT);

        self::assertSame(0, $provider->calls);
        self::assertSame(ProviderInvocationStatus::Accepted, $outcome->status);
        self::assertSame(ProviderBindingStatus::Acknowledged, $outcome->run->providerBindingStatus);
    }

    #[Test]
    public function unavailable_or_not_found_reconciliation_cannot_overwrite_concurrent_acceptance(): void
    {
        foreach ([ProviderLookupStatus::Unavailable, ProviderLookupStatus::NotFound] as $lookupStatus) {
            [$run, , $request] = $this->fixture('reconcile-race-'.$lookupStatus->value);
            $provider = new FakeProviderDispatch(ProviderDispatchResult::acknowledgementUncertain('response lost'));
            [$coordinator, $persistence] = $this->coordinator($run, $provider);
            $uncertain = $coordinator->dispatch($run, $request, RunIdentityFixtures::DISPATCHED_AT);
            $ack = RunIdentityFixtures::acknowledgement('reconcile-race-'.$lookupStatus->value);
            $persistence->beforeCas = function () use ($persistence, $uncertain, $request, $ack): void {
                $current = $persistence->find($request->invocationId);
                $acceptedRun = (new \Sifrious\Logres\ProviderExecutionBinder)->acknowledge($uncertain->run, $ack)->run;
                $persistence->force($current->record(ProviderDispatchResult::accepted($ack)), $acceptedRun);
            };
            $lookup = new class($lookupStatus) implements ProviderExecutionLookup {
                public function __construct(private readonly ProviderLookupStatus $status) {}
                public function find(Run $run): ProviderExecutionLookupResult
                {
                    return new ProviderExecutionLookupResult($this->status, reason: 'not authoritative');
                }
            };

            $result = $coordinator->reconcile($uncertain->run, $lookup);

            self::assertSame(ProviderBindingOutcome::Duplicate, $result->outcome);
            self::assertSame(ProviderBindingStatus::Acknowledged, $persistence->find($run->id)?->providerBindingStatus);
            self::assertSame(ProviderInvocationStatus::Accepted, $persistence->find($request->invocationId)?->status);
        }
    }

    private function fixture(string $suffix = '001'): array
    {
        $run = RunIdentityFixtures::run("invocation-{$suffix}");
        $prompt = (new TaskPromptCompiler)->compile(TaskPromptFixtures::input());
        $request = $this->request($run, $prompt, $suffix);

        return [$run, $prompt, $request];
    }

    private function request(Run $run, TaskPrompt $prompt, string $suffix, string $adapter = 'codex'): ProviderInvocationRequest
    {
        return new ProviderInvocationRequest(
            invocationId: "provider-invocation:{$suffix}",
            runId: $run->id,
            requestId: $run->provenance->requestId,
            taskId: $run->provenance->taskId,
            attemptId: new AttemptId("attempt:{$suffix}:1"),
            idempotencyKey: "dispatch:{$suffix}",
            targetId: $run->provenance->targetSelection->target->id,
            provider: $run->provenance->targetSelection->target->provider,
            agentAdapter: $adapter,
            prompt: $prompt,
            workspaceInstructions: ['Use the frozen workspace only.'],
            eventDelivery: ['callback' => '/events', 'stream' => 'events:'.$suffix],
            inputResponse: ['callback' => '/input', 'correlation' => 'input:'.$suffix],
            timeoutSeconds: $prompt->input->request->constraints->timeoutSeconds,
            cancellationReference: "cancel:invocation-{$suffix}",
        );
    }

    private function coordinator(Run $run, FakeProviderDispatch $provider): array
    {
        $invocations = new InMemoryProviderInvocationStore($run);
        $provider->store = $invocations;

        return [new ProviderInvocationCoordinator($invocations, $provider), $invocations, $invocations];
    }
}

final class FakeProviderDispatch implements ProviderDispatch
{
    public int $calls = 0;
    public ?InMemoryProviderInvocationStore $store = null;
    public ?ProviderInvocationRecord $recordObservedDuringCall = null;

    public function __construct(private readonly ProviderDispatchResult|\Throwable $result) {}

    public function dispatch(ProviderInvocationRequest $request): ProviderDispatchResult
    {
        ++$this->calls;
        $this->recordObservedDuringCall = $this->store?->find($request->invocationId);

        if ($this->result instanceof \Throwable) {
            throw $this->result;
        }

        return $this->result;
    }
}

final class InMemoryProviderInvocationStore implements ProviderInvocationPersistence
{
    /** @var array<string, ProviderInvocationRecord> */ private array $records = [];
    /** @var array<string, string> */ private array $idempotency = [];
    /** @var array<string, Run> */ private array $runs = [];
    public bool $failReservation = false;
    public ?int $failTransitionNumber = null;
    private int $transitionCount = 0;
    public ?\Closure $beforeCas = null;

    public function __construct(Run $run)
    {
        $this->runs[$run->id->value] = $run;
    }

    public function reserve(Run $awaitingRun, ProviderInvocationRequest $request): ProviderInvocationReservation
    {
        $existingId = $this->idempotency[$request->idempotencyKey] ?? null;
        $existing = $this->records[$request->invocationId] ?? ($existingId === null ? null : $this->records[$existingId]);
        if ($existing !== null) {
            return new ProviderInvocationReservation(false, $existing);
        }

        if ($this->failReservation) {
            throw new \RuntimeException('reservation transaction failed');
        }

        $record = new ProviderInvocationRecord($request);
        $this->records[$request->invocationId] = $record;
        $this->idempotency[$request->idempotencyKey] = $request->invocationId;
        $this->runs[$awaitingRun->id->value] = $awaitingRun;

        return new ProviderInvocationReservation(true, $record);
    }

    public function findInvocationByIdempotencyKey(string $idempotencyKey): ?ProviderInvocationRecord
    {
        $invocationId = $this->idempotency[$idempotencyKey] ?? null;

        return $invocationId === null ? null : $this->records[$invocationId];
    }

    public function transition(ProviderInvocationRecord $record, Run $run, ProviderInvocationStatus $expectedStatus, int $expectedVersion): bool
    {
        ++$this->transitionCount;
        if ($this->failTransitionNumber === $this->transitionCount) {
            throw new \RuntimeException('transition transaction failed');
        }
        if ($this->beforeCas !== null) {
            $callback = $this->beforeCas;
            $this->beforeCas = null;
            $callback();
        }
        $current = $this->records[$record->request->invocationId] ?? null;
        if ($current === null || $current->status !== $expectedStatus || $current->version !== $expectedVersion) {
            return false;
        }
        $this->records[$record->request->invocationId] = $record;
        $this->runs[$run->id->value] = $run;

        return true;
    }

    public function force(ProviderInvocationRecord $record, Run $run): void
    {
        $this->records[$record->request->invocationId] = $record;
        $this->runs[$run->id->value] = $run;
    }

    public function findRun(\Sifrious\Logres\RunId $runId): ?Run
    {
        return $this->runs[$runId->value] ?? null;
    }

    public function findRunByProviderExecutionId(ProviderExecutionId $providerExecutionId): ?Run
    {
        foreach ($this->runs as $run) {
            if ($run->providerExecutionId?->canonical() === $providerExecutionId->canonical()) {
                return $run;
            }
        }
        return null;
    }

    public function findInvocation(string $invocationId): ?ProviderInvocationRecord
    {
        return $this->records[$invocationId] ?? null;
    }

    public function findInvocationByRunId(\Sifrious\Logres\RunId $runId): ?ProviderInvocationRecord
    {
        foreach ($this->records as $record) {
            if ($record->request->runId->value === $runId->value) {
                return $record;
            }
        }
        return null;
    }

    public function find(\Sifrious\Logres\RunId|string $identity): ProviderInvocationRecord|Run|null
    {
        if ($identity instanceof \Sifrious\Logres\RunId) {
            return $this->findRun($identity);
        }
        return $this->records[$identity] ?? null;
    }
}
